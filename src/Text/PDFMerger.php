<?php

namespace Osimatic\Text;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Utility class for merging multiple PDF files into a single PDF document.
 * Uses PDFtk (PDF Toolkit) command-line tool to concatenate PDF files.
 * For PDF generation, see PDFGenerator. For PDF validation, see PDF. For PDF conversion, see PDFConverter.
 */
class PDFMerger
{
	/**
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 * @param string|null $pdfToolkitBinaryPath
	 */
	public function __construct(
		private LoggerInterface $logger = new NullLogger(),
		private ?string $pdfToolkitBinaryPath = null,
	) {}

	/**
	 * Sets the logger for error and debugging information.
	 * @param LoggerInterface $logger The logger instance
	 * @return self Returns this instance for method chaining
	 */
	public function setLogger(LoggerInterface $logger): self
	{
		$this->logger = $logger;

		return $this;
	}

	/**
	 * Sets the path to the PDFtk binary executable.
	 * @param string $pdfToolkitBinaryPath The full path to the PDFtk binary
	 * @return self Returns this instance for method chaining
	 */
	public function setPdfToolkitBinaryPath(string $pdfToolkitBinaryPath): self
	{
		$this->pdfToolkitBinaryPath = $pdfToolkitBinaryPath;

		return $this;
	}

	/**
	 * Gets the path to the PDFtk binary executable.
	 * @param bool $trim Whether to remove surrounding quotes from the path (default: true)
	 * @return string The full path to the PDFtk binary
	 */
	public function getPdfToolkitBinaryPath(bool $trim = true): string
	{
		$path = $this->pdfToolkitBinaryPath ?? 'pdftk';
		return $trim ? \Osimatic\FileSystem\FileSystem::stripQuotes($path) : $path;
	}

	/**
	 * Merges multiple PDF files into a single PDF document.
	 * Handles large lists by splitting into multiple merge operations to avoid command line length limits.
	 * @param array $listPdfPath Array of file paths to the PDF files to merge
	 * @param string $newPdfPath The path where the merged PDF file will be created
	 * @param int $profondeur Recursion depth for internal splitting (default: 0, for initial call)
	 * @return bool True if the merge was successful, false on error
	 */
	public function mergeFiles(array $listPdfPath, string $newPdfPath, int $profondeur=0): bool
	{
		$this->logger->info('Merging '.count($listPdfPath).' PDF files into a single PDF file "'.$newPdfPath.'".');

		\Osimatic\FileSystem\FileSystem::initializeFile($newPdfPath);

		// Check that all files exist
		if ($profondeur === 0) {
			foreach ($listPdfPath as $pdfPath) {
				if (!file_exists($pdfPath)) {
					$this->logger->error('PDF file "'.$pdfPath.'" to merge does not exist.');
					return false;
				}
			}
		}

		// Build the command line
		$pdfList = [];
		$nbFile = 0;
		while (($pdfPath = array_shift($listPdfPath)) !== null) {
			$pdfList[] = $pdfPath;
			$nbFile++;

			// TODO: base this on filename length (command line is limited to a certain number of characters)
			if ($nbFile >= 20 && !empty($listPdfPath)) {
				$filePathTemp = sys_get_temp_dir().'/'.uniqid(md5(mt_rand()), true).PDF::FILE_EXTENSION;
				$this->mergeFiles($listPdfPath, $filePathTemp, $profondeur+1);

				$pdfList[] = $filePathTemp;
				break;
			}
		}

		return (new \Osimatic\System\Command($this->logger))->run(array_merge(
			[$this->getPdfToolkitBinaryPath()],
				$pdfList,
				[
					'cat',
					'output',
					$newPdfPath,
					'dont_ask'
				]
		));
	}

}