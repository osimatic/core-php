<?php

namespace Osimatic\Text;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Utility class for generating PDF files from HTML content.
 * Uses wkhtmltopdf library via Snappy to convert HTML to PDF with support for headers, footers, and custom options.
 * For PDF validation, see PDF. For PDF conversion, see PDFConverter. For PDF merging, see PDFMerger.
 */
class PDFGenerator
{
	/**
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 * @param string|null $wkHtmlToPdtBinaryPath
	 */
	public function __construct(
		private LoggerInterface $logger = new NullLogger(),
		private ?string $wkHtmlToPdtBinaryPath = null,
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
	 * Sets the path to the wkhtmltopdf binary executable.
	 * @param string $wkHtmlToPdtBinaryPath The full path to the wkhtmltopdf binary
	 * @return self Returns this instance for method chaining
	 */
	public function setWkHtmlToPdtBinaryPath(string $wkHtmlToPdtBinaryPath): self
	{
		$this->wkHtmlToPdtBinaryPath = $wkHtmlToPdtBinaryPath;

		return $this;
	}

	/**
	 * Generates a PDF file from HTML content with optional header and footer.
	 * @param string $filePath The path where the PDF file will be created
	 * @param string $bodyHtml The HTML content for the main body of the PDF
	 * @param string|null $headerHtml Optional HTML content for the PDF header
	 * @param string|null $footerHtml Optional HTML content for the PDF footer
	 * @param array $options Additional wkhtmltopdf options (see Snappy documentation)
	 * @return bool True if the PDF was successfully generated, false on error
	 */
	public function generateFile(string $filePath, string $bodyHtml, ?string $headerHtml=null, ?string $footerHtml=null, array $options=[]): bool
	{
		$filePath = \Osimatic\FileSystem\FileSystem::formatPath($filePath);
		\Osimatic\FileSystem\FileSystem::initializeFile($filePath);

		$snappy = new \Knp\Snappy\Pdf();
		$snappy->setBinary(\Osimatic\FileSystem\FileSystem::stripQuotes($this->wkHtmlToPdtBinaryPath ?? ''));
		$snappy->setLogger($this->logger);
		if (!empty($headerHtml)) {
			$snappy->setOption('header-html', $headerHtml);
		}
		if (!empty($footerHtml)) {
			$snappy->setOption('footer-html', $footerHtml);
		}
		$snappy->setOption('enable-local-file-access', true);

		try {
			$snappy->generateFromHtml($bodyHtml, $filePath, $options);
		}
		catch (\Exception $e) {
			$this->logger->error('Exception during PDF file generation: '.$e->getMessage());
			return false;
		}

		return true;
	}

}