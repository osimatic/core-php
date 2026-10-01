<?php

namespace Osimatic\Invoice;

use Osimatic\FileSystem\FileSystem;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Merges a regular invoice PDF with a CII XML document to produce a Factur-X hybrid PDF/A-3 file.
 * @link https://github.com/atgp/factur-x atgp/factur-x documentation
 */
readonly class FacturXGenerator
{
	public function __construct(
		private CiiXmlGenerator $ciiXmlGenerator = new CiiXmlGenerator(),
		private LoggerInterface $logger = new NullLogger(),
		private \Atgp\FacturX\Writer $writer = new \Atgp\FacturX\Writer(),
	) {}

	/**
	 * @param InvoiceInterface $invoice
	 * @param string $pdfFilePath Path to the regular invoice PDF, already generated (e.g. by Osimatic\Text\PDFGenerator)
	 * @param string $outputFilePath Path where the Factur-X file will be written
	 * @return string|null The output file path, or null on failure
	 */
	public function generate(InvoiceInterface $invoice, string $pdfFilePath, string $outputFilePath): ?string
	{
		if (null === ($xml = $this->ciiXmlGenerator->generate($invoice))) {
			return null;
		}

		if (!is_readable($pdfFilePath) || false === ($pdfContent = file_get_contents($pdfFilePath))) {
			$this->logger->error('Unable to read the invoice PDF file to merge: '.$pdfFilePath);
			return null;
		}

		try {
			$facturXContent = $this->writer->generate($pdfContent, $xml);

			$outputFilePath = FileSystem::formatPath($outputFilePath);
			FileSystem::initializeFile($outputFilePath);
			if (false === file_put_contents($outputFilePath, $facturXContent)) {
				throw new \RuntimeException('Unable to write the file: '.$outputFilePath);
			}
		}
		catch (\Throwable $e) {
			$this->logger->error('Failed to generate the Factur-X document: '.$e->getMessage(), ['exception' => $e, 'outputFilePath' => $outputFilePath]);
			return null;
		}

		return $outputFilePath;
	}
}