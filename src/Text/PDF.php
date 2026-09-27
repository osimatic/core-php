<?php

namespace Osimatic\Text;

use Osimatic\FileSystem\OutputFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Utility class for working with PDF (Portable Document Format) files.
 * Provides methods for validating, outputting and extracting information from PDF files.
 * For PDF generation, see PDFGenerator. For PDF conversion, see PDFConverter. For PDF merging, see PDFMerger.
 */
class PDF
{
	public const string FILE_EXTENSION = '.pdf';
	public const array MIME_TYPES = [
		'application/pdf',
		'application/x-pdf',
		'application/vnd.cups-pdf',
		'application/vnd.sealedmedia.softseal.pdf',
	];

	// ========== Validation ==========

	/**
	 * Checks if a file is a valid PDF file based on extension, MIME type, and PDF structure.
	 * Validates that the file starts with "%PDF-" header and ends with "%%EOF".
	 * @param string $filePath The path to the file to check
	 * @param string $clientOriginalName The original file name from client
	 * @return bool True if the file is a valid PDF file, false otherwise
	 */
	public static function checkFile(string $filePath, string $clientOriginalName): bool
	{
		// First check extension and MIME type
		if (!\Osimatic\FileSystem\File::check($filePath, $clientOriginalName, [self::FILE_EXTENSION], self::MIME_TYPES)) {
			return false;
		}

		// Then verify PDF structure
		if (!file_exists($filePath) || !is_readable($filePath)) {
			return false;
		}

		// Check file header (must start with "%PDF-")
		$handle = fopen($filePath, 'rb');
		if ($handle === false) {
			return false;
		}

		$header = fread($handle, 5);
		fclose($handle);

		if ($header !== '%PDF-') {
			return false;
		}

		// Check file footer (should end with "%%EOF")
		$handle = fopen($filePath, 'rb');
		if ($handle === false) {
			return false;
		}

		fseek($handle, -1024, SEEK_END); // Read last 1KB
		$footer = fread($handle, 1024);
		fclose($handle);

		return str_contains($footer, '%%EOF');
	}

	// ========== Metadata ==========

	/**
	 * Get the file size of a PDF in bytes.
	 * @param string $filePath The path to the PDF file
	 * @return int|null The file size in bytes, or null if file doesn't exist
	 */
	public static function getFileSize(string $filePath): ?int
	{
		if (!file_exists($filePath)) {
			return null;
		}

		$size = filesize($filePath);
		return $size !== false ? $size : null;
	}

	/**
	 * Get the number of pages in a PDF file.
	 * Uses a simple regex-based approach to count page objects in the PDF structure.
	 * @param string $filePath The path to the PDF file
	 * @return int|null The number of pages, or null if file doesn't exist or cannot be read
	 */
	public static function getPageCount(string $filePath): ?int
	{
		if (!file_exists($filePath) || !is_readable($filePath)) {
			return null;
		}

		$content = file_get_contents($filePath);
		if ($content === false) {
			return null;
		}

		// Method 1: Try to find /Count in the root /Pages tree object (not e.g. an unrelated /Outlines /Count)
		if (preg_match('/\/Type\s*\/Pages\b[\s\S]*?\/Count\s+(\d+)/', $content, $matches)) {
			return (int) $matches[1];
		}

		// Method 2: Count /Type /Page occurrences (excluding /Pages)
		$pageCount = preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
		if ($pageCount > 0) {
			return $pageCount;
		}

		return null;
	}

	/**
	 * Extract metadata from a PDF file.
	 * Returns an array with available metadata such as title, author, creator, creation date, etc.
	 * @param string $filePath The path to the PDF file
	 * @return array|null An associative array of metadata, or null if file doesn't exist or cannot be read
	 */
	public static function getMetadata(string $filePath): ?array
	{
		if (!file_exists($filePath) || !is_readable($filePath)) {
			return null;
		}

		$content = file_get_contents($filePath);
		if ($content === false) {
			return null;
		}

		$metadata = [];

		// Extract PDF version
		if (preg_match('/%PDF-(\d+\.\d+)/', $content, $matches)) {
			$metadata['version'] = $matches[1];
		}

		// Extract common metadata fields
		$fields = [
			'Title' => 'title',
			'Author' => 'author',
			'Subject' => 'subject',
			'Creator' => 'creator',
			'Producer' => 'producer',
			'CreationDate' => 'creation_date',
			'ModDate' => 'modification_date',
			'Keywords' => 'keywords',
		];

		foreach ($fields as $pdfField => $arrayKey) {
			if (preg_match('/\/' . $pdfField . '\s*\(([^)]+)\)/', $content, $matches)) {
				$value = $matches[1];
				// Decode PDF string (basic decoding)
				$value = self::decodePdfString($value);
				$metadata[$arrayKey] = $value;
			}
		}

		// Add page count
		$pageCount = self::getPageCount($filePath);
		if ($pageCount !== null) {
			$metadata['page_count'] = $pageCount;
		}

		// Add file size
		$fileSize = self::getFileSize($filePath);
		if ($fileSize !== null) {
			$metadata['file_size'] = $fileSize;
		}

		return !empty($metadata) ? $metadata : null;
	}

	/**
	 * Decode a PDF string by removing escape sequences and handling basic encoding.
	 * @param string $str The PDF string to decode
	 * @return string The decoded string
	 */
	private static function decodePdfString(string $str): string
	{
		// Remove escape sequences
		$str = str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'], ['(', ')', '\\', "\n", "\r", "\t"], $str);

		// Handle UTF-16BE BOM (Byte Order Mark)
		if (str_starts_with($str, "\xFE\xFF")) {
			$str = mb_convert_encoding(substr($str, 2), 'UTF-8', 'UTF-16BE');
		}

		return $str;
	}

	// ========== Output ==========

	/**
	 * Outputs a PDF file to the client browser for inline viewing.
	 * No output should be performed before or after calling this function.
	 * @param string $filePath The path to the PDF file to output
	 * @param string|null $fileName Optional file name to send to the client
	 */
	public static function output(string $filePath, ?string $fileName=null): void
	{
		\Osimatic\FileSystem\File::output($filePath, $fileName, 'application/pdf');
	}

	/**
	 * Outputs a PDF file to the client browser for inline viewing.
	 * No output should be performed before or after calling this function.
	 * @param OutputFile $file The OutputFile object containing the file to output
	 */
	public static function outputFile(OutputFile $file): void
	{
		\Osimatic\FileSystem\File::outputFile($file, 'application/pdf');
	}

	/**
	 * Forces download of a PDF file to the client browser.
	 * No output should be performed before or after calling this function.
	 * @param string $filePath The path to the PDF file to download
	 * @param string|null $fileName Optional file name for the download
	 */
	public static function download(string $filePath, ?string $fileName=null): void
	{
		\Osimatic\FileSystem\File::download($filePath, $fileName);
	}

	/**
	 * Forces download of a PDF file to the client browser.
	 * No output should be performed before or after calling this function.
	 * @param OutputFile $file The OutputFile object containing the file to download
	 */
	public static function downloadFile(OutputFile $file): void
	{
		\Osimatic\FileSystem\File::downloadFile($file);
	}

	/**
	 * Creates an HTTP response for a PDF file.
	 * @param string $filePath The path to the PDF file
	 * @param string|null $fileName Optional file name for the response
	 * @return Response The HTTP response object
	 */
	public static function getHttpResponse(string $filePath, ?string $fileName=null): Response
	{
		return \Osimatic\FileSystem\File::getHttpResponse($filePath, $fileName, false, 'application/pdf');
	}

}