<?php

namespace Osimatic\Text;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Utility class for generating CSV files from PHP arrays.
 * Uses Symfony Serializer to convert data to CSV format with proper UTF-8 BOM for Excel compatibility.
 * For CSV validation and parsing, see CSV.
 */
class CSVGenerator
{
	/**
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 */
	public function __construct(
		private LoggerInterface $logger=new NullLogger(),
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
	 * Generates a CSV file from a PHP array with semicolon delimiter and UTF-8 BOM.
	 * @param string $filePath The path where the CSV file will be created
	 * @param array $data The data array to convert to CSV format
	 * @param string|null $title Optional title row to add at the beginning of the file
	 * @param array $context Additional Symfony Serializer context options (CsvEncoder options)
	 * @return bool True if the CSV file was successfully generated, false on error
	 */
	public function generateFile(string $filePath, array $data, ?string $title=null, array $context=[]): bool
	{
		\Osimatic\FileSystem\FileSystem::initializeFile($filePath);

		$serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder([CsvEncoder::DELIMITER_KEY => ';', CsvEncoder::OUTPUT_UTF8_BOM_KEY => false])]);

		if (!empty($title)) {
			$data = array_merge([[$title]], $data);
		}

		$str = $serializer->encode($data, 'csv', $context);
		$str = substr($str, strpos($str, "\n")+strlen("\n"));

		if ($context[CsvEncoder::OUTPUT_UTF8_BOM_KEY] ?? true) {
			$str = chr(0xEF).chr(0xBB).chr(0xBF).$str; // UTF-8 BOM to force UTF-8 encoding in Excel
		}

		$nbBytes = file_put_contents($filePath, $str, FILE_APPEND);

		if (false === $nbBytes) {
			$this->logger->error('Error writing CSV file: '.$filePath);
			return false;
		}

		$this->logger->info('New CSV file generated: '.$filePath.'. Nb bytes: '.$nbBytes);
		return true;
	}
}