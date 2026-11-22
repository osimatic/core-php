<?php

namespace Osimatic\Text;

use Osimatic\FileSystem\OutputFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Utility class for working with CSV (Comma-Separated Values) files.
 * Provides methods for reading, writing, validating and outputting CSV data.
 */
class CSV
{
	public const string FILE_EXTENSION = '.csv';
	public const array MIME_TYPES = [
		'text/csv',
		'txt/csv',
		'application/octet-stream',
		'application/csv-tab-delimited-table',
		'application/vnd.ms-excel',
		'application/vnd.ms-pki.seccat',
		'text/plain',
	];

	public const string DELIMITER_COMMA = ',';
	public const string DELIMITER_SEMICOLON = ';';
	public const string DELIMITER_TAB = "\t";

	// ========== Validation ==========

	/**
	 * Checks if a file is a valid CSV file based on extension and MIME type.
	 * @param string $filePath The path to the file to check
	 * @param string $clientOriginalName The original file name from client
	 * @return bool True if the file is a valid CSV file, false otherwise
	 */
	public static function checkFile(string $filePath, string $clientOriginalName): bool
	{
		return \Osimatic\FileSystem\File::check($filePath, $clientOriginalName, [self::FILE_EXTENSION], self::MIME_TYPES);
	}

	// ========== Output ==========

	/**
	 * Outputs a CSV file to the client browser.
	 * No output should be performed before or after calling this function.
	 * @param string $filePath The path to the CSV file to output
	 * @param string|null $fileName Optional file name to send to the client
	 */
	public static function output(string $filePath, ?string $fileName=null): void
	{
		\Osimatic\FileSystem\File::output($filePath, $fileName, 'text/csv');
	}

	/**
	 * Outputs a CSV file to the client browser.
	 * No output should be performed before or after calling this function.
	 * @param OutputFile $file The OutputFile object containing the file to output
	 */
	public static function outputFile(OutputFile $file): void
	{
		\Osimatic\FileSystem\File::outputFile($file, 'text/csv');
	}

	/**
	 * Creates an HTTP response for a CSV file.
	 * @param string $filePath The path to the CSV file
	 * @param string|null $fileName Optional file name for the response
	 * @return Response The HTTP response object
	 */
	public static function getHttpResponse(string $filePath, ?string $fileName=null): Response
	{
		return \Osimatic\FileSystem\File::getHttpResponse($filePath, $fileName, false, 'text/csv');
	}

	// ========== Conversion ==========

	/**
	 * Converts a CSV file to an associative array.
	 * The first row is treated as the header and used as array keys.
	 * @link http://gist.github.com/385876
	 * @param string $filename The path to the CSV file
	 * @param string $delimiter The field delimiter character (default: comma)
	 * @param bool $skipEmptyLines Skip empty lines in the CSV file (default: false)
	 * @param bool $trimValues Trim whitespace from field values (default: false)
	 * @return array|null Array of associative arrays, or null if file doesn't exist or can't be read
	 */
	public static function convertToArray(string $filename, string $delimiter=',', bool $skipEmptyLines=false, bool $trimValues=false): ?array
	{
		if (!file_exists($filename) || !is_readable($filename)) {
			return null;
		}

		$header = null;
		$data = [];

		if (false === ($handle = fopen($filename, 'rb'))) {
			return null;
		}

		// Detect and skip BOM if present
		$bom = fread($handle, 3);
		if ($bom !== "\xEF\xBB\xBF") {
			rewind($handle);
		}

		while (($row = fgetcsv($handle, 0, separator: $delimiter, escape: "")) !== false) {
			// Skip empty lines if requested
			if ($skipEmptyLines && count(array_filter($row, static fn($v) => $v !== null && $v !== '')) === 0) {
				continue;
			}

			if ($trimValues) {
				$row = array_map('trim', $row);
			}

			if (!$header) {
				$header = $row;
			}
			else {
				$data[] = array_combine($header, $row);
			}
		}
		fclose($handle);

		return $data;
	}

	/**
	 * Converts an array to a CSV string.
	 * @param array $data Array of associative arrays to convert
	 * @param string $delimiter The field delimiter character (default: comma)
	 * @param string $enclosure The field enclosure character (default: double quote)
	 * @param bool $includeHeader Include header row with array keys (default: true)
	 * @return string The CSV string
	 */
	public static function arrayToCsv(array $data, string $delimiter=',', string $enclosure='"', bool $includeHeader=true): string
	{
		if (empty($data)) {
			return '';
		}

		$output = fopen('php://temp', 'r+');

		if ($includeHeader) {
			$firstRow = reset($data);
			if (is_array($firstRow)) {
				fputcsv($output, array_keys($firstRow), $delimiter, $enclosure);
			}
		}

		foreach ($data as $row) {
			if (is_array($row)) {
				fputcsv($output, $row, $delimiter, $enclosure);
			}
		}

		rewind($output);
		$csv = stream_get_contents($output);
		fclose($output);

		return $csv;
	}

	// ========== Utilities ==========

	/**
	 * Parses and normalizes a separator value to a valid CSV delimiter.
	 * @param string|null $value The separator value to parse
	 * @return string The normalized delimiter (comma or semicolon)
	 */
	public static function parseSeparator(?string $value): string
	{
		return ';' === $value ? ';' : ',';
	}

	/**
	 * Neutralizes a text value that a spreadsheet application would interpret as a formula, by prefixing it with an apostrophe.
	 * Values starting with "=", "+", "-", "@", a tab or a carriage return are escaped to prevent CSV formula injection.
	 * @param string $value The text value to write in a cell
	 * @return string The value, safe to write as text in a cell
	 * @link https://owasp.org/www-community/attacks/CSV_Injection
	 */
	public static function neutralizeFormula(string $value): string
	{
		return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
	}

	/**
	 * Forces a value to be treated as a string in Excel by adding ="value" format.
	 * Useful for preventing Excel from auto-converting values (like leading zeros).
	 * @param string|int|float|null $value The value to format
	 * @return string The Excel-formatted string
	 */
	public static function forceStringForExcel(string|int|float|null $value): string
	{
		return null !== $value && '' !== $value ? '="'.$value.'"' : '';
	}
}