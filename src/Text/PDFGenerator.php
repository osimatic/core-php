<?php

namespace Osimatic\Text;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Utility class for generating PDF files from HTML content.
 * Uses Dompdf to convert HTML to PDF with support for headers, footers, and custom options.
 * For PDF validation, see PDF. For PDF conversion, see PDFConverter. For PDF merging, see PDFMerger.
 */
class PDFGenerator
{
	/**
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 */
	public function __construct(
		private LoggerInterface $logger = new NullLogger(),
	) {}

	/**
	 * Sets the logger for error and debugging information.
	 * @param LoggerInterface $logger The PSR-3 logger instance
	 * @return self Returns this instance for method chaining
	 */
	public function setLogger(LoggerInterface $logger): self
	{
		$this->logger = $logger;

		return $this;
	}

	/**
	 * Generates a PDF file from HTML content with optional header and footer.
	 * The header and footer HTML are repeated on every page (rendered as CSS "position: fixed" blocks), reproducing the behavior of wkhtmltopdf's former --header-html/--footer-html options.
	 * @param string $filePath The path where the PDF file will be created
	 * @param string $bodyHtml The HTML content for the main body of the PDF
	 * @param string|null $headerHtml Optional HTML content for the PDF header, repeated on every page
	 * @param string|null $footerHtml Optional HTML content for the PDF footer, repeated on every page
	 * @param array $options Dompdf options (see \Dompdf\Options), plus the legacy 'orientation' key ('portrait'|'landscape')
	 * @return bool True if the PDF was successfully generated, false on error
	 * @link https://github.com/dompdf/dompdf/wiki/Usage Dompdf usage documentation
	 */
	public function generateFile(string $filePath, string $bodyHtml, ?string $headerHtml=null, ?string $footerHtml=null, array $options=[]): bool
	{
		$filePath = \Osimatic\FileSystem\FileSystem::formatPath($filePath);
		\Osimatic\FileSystem\FileSystem::initializeFile($filePath);

		$orientation = strtolower((string) ($options['orientation'] ?? 'portrait'));
		unset($options['orientation']);

		try {
			$dompdf = new \Dompdf\Dompdf($options + [
				'isRemoteEnabled' => true,
				'isHtml5ParserEnabled' => true,
				'chroot' => DIRECTORY_SEPARATOR,
			]);
			$dompdf->setPaper('a4', $orientation);
			$dompdf->loadHtml($this->buildHtmlWithHeaderAndFooter($bodyHtml, $headerHtml, $footerHtml), 'UTF-8');
			$dompdf->render();

			if (false === file_put_contents($filePath, $dompdf->output())) {
				$this->logger->error('Failed to write generated PDF file: '.$filePath);
				return false;
			}
		}
		catch (\Exception $e) {
			$this->logger->error('Exception during PDF file generation: '.$e->getMessage());
			return false;
		}

		return true;
	}

	/**
	 * Injects the header and footer HTML as repeating "position: fixed" blocks inside the body document, so that they appear on every page.
	 * @param string $bodyHtml
	 * @param string|null $headerHtml
	 * @param string|null $footerHtml
	 * @return string
	 */
	private function buildHtmlWithHeaderAndFooter(string $bodyHtml, ?string $headerHtml, ?string $footerHtml): string
	{
		$html = $bodyHtml;

		if (!empty($headerHtml)) {
			$headerBlock = '<div style="position: fixed; top: 0; left: 0; right: 0;">'.$this->extractBodyContent($headerHtml).'</div>';
			$html = preg_match('/<body[^>]*>/i', $html) ? preg_replace('/<body[^>]*>/i', '$0'.$headerBlock, $html, 1) : $headerBlock.$html;
		}

		if (!empty($footerHtml)) {
			$footerBlock = '<div style="position: fixed; bottom: 0; left: 0; right: 0;">'.$this->extractBodyContent($footerHtml).'</div>';
			$html = preg_match('/<\/body>/i', $html) ? preg_replace('/<\/body>/i', $footerBlock.'$0', $html, 1) : $html.$footerBlock;
		}

		return $html;
	}

	/**
	 * Extracts the inner content of the <body> tag from a full HTML document, or returns the string unchanged if it is already a fragment without a <body> tag.
	 * @param string $html
	 * @return string
	 */
	private function extractBodyContent(string $html): string
	{
		if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
			return $matches[1];
		}

		return $html;
	}

}
