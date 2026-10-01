<?php

namespace Osimatic\Text;

use LaLit\Array2XML;
use LaLit\XML2Array;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Utility class for converting between XML and PHP array formats.
 * Provides bidirectional conversion using LaLit XML libraries and a legacy conversion method.
 * For XML file generation, see XMLGenerator. For XML validation, see XML.
 */
class XMLConverter
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
	 * Converts a PHP array to an XML string.
	 * @param array $array The PHP array to convert
	 * @param string $firstTag The name of the root XML element
	 * @param array $docType Optional document type declaration parameters
	 * @return string|null The XML string, or null on error
	 */
	public function convertFromArray(array $array, string $firstTag, array $docType = []): ?string
	{
		try {
			$xml = Array2XML::createXML($firstTag, $array, $docType);
			return $xml?->saveXML();
		}
		catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}
		return null;
	}

	/**
	 * Converts an XML string to a PHP array.
	 * @param string $xmlContent The XML string to convert
	 * @return array|null The PHP array representation of the XML, or null on error
	 */
	public function convertToArray(string $xmlContent): ?array
	{
		if (empty($xmlContent)) {
			return null;
		}

		try {
			return XML2Array::createArray($xmlContent);
		}
		catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}
		return null;
	}

	/**
	 * Converts XML text to an array using a legacy parsing method.
	 * This is an alternative conversion method that provides different array structure options.
	 * @link http://www.bin-co.com/php/scripts/xml2array/
	 * @param string $contents The XML text to parse
	 * @param bool $get_attributes If true, includes XML attributes in the result (changes array structure)
	 * @param string $priority Can be 'tag' or 'attribute' - determines whether tags or attributes have priority in the array structure
	 * @return array The parsed XML as a PHP array. Use print_r() to see the resulting structure.
	 * @example $array = XMLConverter::convertToArrayOld(file_get_contents('feed.xml'));
	 * @example $array = XMLConverter::convertToArrayOld(file_get_contents('feed.xml'), true, 'attribute');
	 */
	public static function convertToArrayOld(string $contents, bool $get_attributes=true, string $priority = 'tag'): array
	{
		if (empty($contents) || !function_exists('xml_parser_create')) {
			return [];
		}

		// Get the XML parser of PHP - PHP must have this module for the parser to work
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, 'UTF-8'); // http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parse_into_struct($parser, trim($contents), $xml_values);
		xml_parser_free($parser);

		if (!$xml_values) {
			return [];
		}

		// Initializations
		$xml_array = [];
		$parents = [];
		$opened_tags = [];
		$arr = [];

		$current = &$xml_array; // Reference

		// Go through the tags
		$repeated_tag_index = []; // Multiple tags with same name will be turned into an array
		foreach($xml_values as $data) {
			unset($attributes, $value); // Remove existing values, or there will be trouble

			// This command will extract these variables into the foreach scope:
			// tag(string), type(string), level(int), attributes(array)
			extract($data); // We could use the array by itself, but this is cooler

			$result = [];
			$attributes_data = [];

			if (isset($value)) {
				if ($priority === 'tag') {
					$result = $value;
				}
				else {
					$result['value'] = $value; // Put the value in a assoc array if we are in the 'Attribute' mode
				}
			}

			// Set the attributes too.
			if (isset($attributes) && $get_attributes) {
				foreach ($attributes as $attr => $val) {
					if ($priority === 'tag') {
						$attributes_data[$attr] = $val;
					}
					else {
						$result['attr'][$attr] = $val; // Set all the attributes in a array called 'attr'
					}
				}
			}

			// See tag status and do the needed.
			if ($type === 'open') { // The starting of the tag '<tag>'
				$parent[$level-1] = &$current;
				if (!is_array($current) || (!array_key_exists($tag, $current))) { //Insert New tag
					$current[$tag] = $result;
					if ($attributes_data) {
						$current[$tag. '_attr'] = $attributes_data;
					}
					$repeated_tag_index[$tag.'_'.$level] = 1;

					$current = &$current[$tag];
				}
				else { // There was another element with the same tag name
					if (isset($current[$tag][0])) { // If there is a 0th element it is already an array
						$current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
						$repeated_tag_index[$tag.'_'.$level]++;
					}
					else { // This section will make the value an array if multiple tags with the same name appear together
						$current[$tag] = [$current[$tag], $result]; // This will combine the existing item and the new item together to make an array
						$repeated_tag_index[$tag.'_'.$level] = 2;

						if (isset($current[$tag.'_attr'])) { // The attribute of the last(0th) tag must be moved as well
							$current[$tag]['0_attr'] = $current[$tag.'_attr'];
							unset($current[$tag.'_attr']);
						}

					}
					$last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
					$current = &$current[$tag][$last_item_index];
				}

			}
			elseif ($type === 'complete') { // Tags that ends in 1 line '<tag />'
				// See if the key is already taken.
				if (!isset($current[$tag])) { // New Key
					$current[$tag] = $result;
					$repeated_tag_index[$tag.'_'.$level] = 1;
					if ($priority === 'tag' && $attributes_data) {
						$current[$tag. '_attr'] = $attributes_data;
					}
				}
				else { // If taken, put all things inside a list(array)
					if (isset($current[$tag][0]) && is_array($current[$tag])) { // If it is already an array...
						// ...push the new element into that array.
						$current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;

						if ($priority === 'tag' && $get_attributes && $attributes_data) {
							$current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
						}
						$repeated_tag_index[$tag.'_'.$level]++;

					}
					else { // If it is not an array...
						$current[$tag] = [$current[$tag], $result]; // ...Make it an array using using the existing value and the new value
						$repeated_tag_index[$tag.'_'.$level] = 1;
						if ($priority === 'tag' && $get_attributes) {
							if (isset($current[$tag.'_attr'])) { // The attribute of the last(0th) tag must be moved as well
								$current[$tag]['0_attr'] = $current[$tag.'_attr'];
								unset($current[$tag.'_attr']);
							}

							if ($attributes_data) {
								$current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
							}
						}
						$repeated_tag_index[$tag.'_'.$level]++; // 0 and 1 index is already taken
					}
				}

			}
			elseif ($type === 'close') { // End of tag '</tag>'
				$current = &$parent[$level-1];
			}
		}

		return($xml_array);
	}
}