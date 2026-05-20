<?php

namespace Osimatic\Text;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Utility class for converting PDF files to and from other formats.
 * Uses ImageMagick (convert command) to convert between PDF and image formats.
 * For PDF generation, see PDFGenerator. For PDF validation, see PDF. For PDF merging, see PDFMerger.
 */
class PDFConverter
{
	/**
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 * @param string|null $imagickConverterBinaryPath
	 */
	public function __construct(
		private LoggerInterface $logger = new NullLogger(),
		private ?string $imagickConverterBinaryPath = null,
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
	 * Sets the path to the ImageMagick convert binary executable.
	 * @param string $imagickConverterBinaryPath The full path to the ImageMagick convert binary
	 * @return self Returns this instance for method chaining
	 */
	public function setImagickConverterBinaryPath(string $imagickConverterBinaryPath): self
	{
		$this->imagickConverterBinaryPath = $imagickConverterBinaryPath;

		return $this;
	}

	/**
	 * Gets the path to the ImageMagick convert binary executable.
	 * @param bool $trim Whether to remove surrounding quotes from the path (default: true)
	 * @return string The full path to the ImageMagick convert binary
	 */
	public function getImagickConverterBinaryPath(bool $trim = true): string
	{
		$path = $this->imagickConverterBinaryPath ?? 'imagick';
		return $trim ? \Osimatic\FileSystem\FileSystem::stripQuotes($path) : $path;
	}

	/**
	 * Converts an image file to PDF format.
	 * @param string $imageFilePath The path to the source image file
	 * @param string $pdfFilePath The path where the PDF file will be created
	 * @return bool True if the conversion was successful, false on error
	 */
	public function convertImageToPdf(string $imageFilePath, string $pdfFilePath): bool
	{
		return (new \Osimatic\System\Command($this->logger))->run([
			$this->getImagickConverterBinaryPath(),
			$imageFilePath,
			$pdfFilePath
		]);

		/*try {
			$pdf = new \Imagick([$imageFilePath]);
			$pdf->setImageFormat('pdf');
			$pdf->writeImages($pdfFilePath, true);
		} catch (\ImagickException $e) {
			$this->logger->error($e->getMessage());
			return false;
		}*/
	}

	/**
	 * Converts a PDF file to image files (one image per page).
	 * @param string $pdfPath The path to the source PDF file
	 * @param string $imagePath The base path for the output image files (page numbers will be appended)
	 * @return bool True if the conversion was successful, false on error
	 */
	public function convertToImages(string $pdfPath, string $imagePath): bool
	{
		if (!file_exists($pdfPath)) {
			return false;
		}

		return (new \Osimatic\System\Command($this->logger))->run([
			$this->getImagickConverterBinaryPath(),
			'-quality', '100',
			'-density', '150',
			$pdfPath,
			$imagePath
		]);
	}

}