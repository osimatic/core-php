<?php

namespace Osimatic\Media;

use Osimatic\FileSystem\OutputFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Audio
 * Provides utilities for handling audio files including format detection, validation, metadata extraction, and streaming playback.
 * Supports multiple audio formats: MP3, WAV, OGG, AAC, AIFF, WMA, and WebM.
 * Uses getID3 library for audio file analysis and metadata extraction.
 */
class Audio
{
	public const string MP3_FORMAT 			= 'mp3';
	public const string MP3_EXTENSION 		= '.mp3';
	public const array MP3_EXTENSIONS 		= ['.mp3', '.mpga', '.mp2', '.mp2a', '.m2a', '.m3a'];
	public const array MP3_MIME_TYPES 		= ['audio/mpeg'];

	public const string WAV_FORMAT 			= 'wav';
	public const string WAV_EXTENSION 		= '.wav';
	public const array WAV_MIME_TYPES 		= ['audio/x-wav'];

	public const string OGG_FORMAT 			= 'ogg';
	public const string OGG_EXTENSION 		= '.ogg';
	public const array OGG_EXTENSIONS 		= ['.ogg', '.oga', '.spx'];
	public const array OGG_MIME_TYPES 		= ['audio/ogg'];

	public const string AAC_FORMAT 			= 'aac';
	public const string AAC_EXTENSION 		= '.aac';
	public const array AAC_MIME_TYPES 		= ['audio/x-aac', 'audio/aac'];

	public const string AIFF_FORMAT 		= 'aiff';
	public const string AIFF_EXTENSION 		= '.aiff';
	public const array AIFF_EXTENSIONS 		= ['.aif', '.aiff', '.aifc'];
	public const array AIFF_MIME_TYPES 		= ['audio/x-aiff'];

	public const string WMA_FORMAT 			= 'wma';
	public const string WMA_EXTENSION 		= '.wma';
	public const array WMA_MIME_TYPES 		= ['audio/x-ms-wma'];

	public const string WEBM_FORMAT 		= 'webm';
	public const string WEBM_EXTENSION 		= '.weba';
	public const array WEBM_MIME_TYPES 		= ['audio/webm'];

	private const int STREAM_BUFFER_SIZE 	= 1024 * 8; // 8 KB buffer for streaming

	private static ?\getID3 $getID3Instance = null;

	/**
	 * Get all supported audio file extensions and their corresponding MIME types.
	 * @return array Associative array mapping format names to [extensions, mime_types] arrays
	 */
	public static function getExtensionsAndMimeTypes(): array
	{
		return [
			'mp3' => [self::MP3_EXTENSIONS, self::MP3_MIME_TYPES],
			'wav' => [[self::WAV_EXTENSION], self::WAV_MIME_TYPES],
			'ogg' => [self::OGG_EXTENSIONS, self::OGG_MIME_TYPES],
			'aac' => [[self::AAC_EXTENSION], self::AAC_MIME_TYPES],
			'aiff' => [self::AIFF_EXTENSIONS, self::AIFF_MIME_TYPES],
			'wma' => [[self::WMA_EXTENSION], self::WMA_MIME_TYPES],
			'weba' => [[self::WEBM_EXTENSION], self::WEBM_MIME_TYPES],
		];
	}

	/**
	 * Get or create the singleton getID3 instance.
	 * @return \getID3 The getID3 instance
	 */
	private static function getID3Instance(): \getID3
	{
		if (self::$getID3Instance === null) {
			self::$getID3Instance = new \getID3();
		}
		return self::$getID3Instance;
	}

	/**
	 * Get audio file information and metadata using getID3 library.
	 * Returns detailed information including format, bitrate, duration, tags, and more.
	 * Uses a singleton getID3 instance for better performance.
	 * @param string $audioFilePath The complete path to the audio file
	 * @param LoggerInterface|null $logger Optional logger to record errors
	 * @return array|null Array containing audio file information, null if file doesn't exist or on error
	 */
	public static function getInfos(string $audioFilePath, ?LoggerInterface $logger=null): ?array
	{
		if (!file_exists($audioFilePath)) {
			return null;
		}

		try {
			return self::getID3Instance()->analyze($audioFilePath);
		} catch (\getid3_exception $e) {
			$logger?->info($e->getMessage());
		}
		return null;
	}

	/**
	 * Get the audio format of a file.
	 * Supports detection of WAV, MP3, OGG, AAC, AIFF, WMA, and WebM formats.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return string|null The format identifier (e.g., WAV_FORMAT, MP3_FORMAT, OGG_FORMAT), null if format is not recognized
	 */
	public static function getFormat(string $audioFilePath): ?string
	{
		if (empty($fileInfos = self::getInfos($audioFilePath))) {
			return null;
		}

		// Check audio dataformat
		if (!empty($fileInfos['audio']['dataformat'])) {
			$dataFormat = mb_strtolower($fileInfos['audio']['dataformat']);

			return match($dataFormat) {
				'wav' => self::WAV_FORMAT,
				'mp3' => self::MP3_FORMAT,
				'aac' => self::AAC_FORMAT,
				'aiff' => self::AIFF_FORMAT,
				'wma', 'asf' => self::WMA_FORMAT,
				default => null,
			};
		}

		// Check file format for container formats
		if (!empty($fileInfos['fileformat'])) {
			$fileFormat = mb_strtolower($fileInfos['fileformat']);

			return match($fileFormat) {
				'webm' => self::WEBM_FORMAT,
				'ogg' => self::OGG_FORMAT,
				default => null,
			};
		}

		return null;
	}

	/**
	 * Get the duration of an audio file in seconds.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return float Duration in seconds, 0 if unable to determine duration
	 */
	public static function getDuration(string $audioFilePath): float
	{
		$infos = self::getInfos($audioFilePath);
		return $infos['playtime_seconds'] ?? 0;
	}

	/**
	 * Get the bitrate of an audio file in bits per second.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return int|null Bitrate in bits per second, null if unable to determine
	 */
	public static function getBitrate(string $audioFilePath): ?int
	{
		$infos = self::getInfos($audioFilePath);
		return $infos['audio']['bitrate'] ?? null;
	}

	/**
	 * Get the sample rate of an audio file in Hz.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return int|null Sample rate in Hz, null if unable to determine
	 */
	public static function getSampleRate(string $audioFilePath): ?int
	{
		$infos = self::getInfos($audioFilePath);
		return $infos['audio']['sample_rate'] ?? null;
	}

	/**
	 * Get the number of audio channels.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return int|null Number of channels (1 for mono, 2 for stereo, etc.), null if unable to determine
	 */
	public static function getChannels(string $audioFilePath): ?int
	{
		$infos = self::getInfos($audioFilePath);
		return $infos['audio']['channels'] ?? null;
	}

	/**
	 * Get the artist name from audio file tags.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return string|null The artist name, null if not available
	 */
	public static function getArtist(string $audioFilePath): ?string
	{
		$infos = self::getInfos($audioFilePath);
		return $infos['tags']['id3v2']['artist'][0] ?? $infos['tags']['id3v1']['artist'][0] ?? null;
	}

	/**
	 * Get the track title from audio file tags.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return string|null The track title, null if not available
	 */
	public static function getTitle(string $audioFilePath): ?string
	{
		$infos = self::getInfos($audioFilePath);
		return $infos['tags']['id3v2']['title'][0] ?? $infos['tags']['id3v1']['title'][0] ?? null;
	}

	/**
	 * Get the album name from audio file tags.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return string|null The album name, null if not available
	 */
	public static function getAlbum(string $audioFilePath): ?string
	{
		$infos = self::getInfos($audioFilePath);
		return $infos['tags']['id3v2']['album'][0] ?? $infos['tags']['id3v1']['album'][0] ?? null;
	}

	/**
	 * Get the release year from audio file tags.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return string|null The release year, null if not available
	 */
	public static function getYear(string $audioFilePath): ?string
	{
		$infos = self::getInfos($audioFilePath);
		return $infos['tags']['id3v2']['year'][0] ?? $infos['tags']['id3v1']['year'][0] ?? null;
	}

	/**
	 * Get all audio tags (artist, title, album, year, etc.) in a single call.
	 * More efficient than calling individual tag methods when multiple tags are needed.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return array Associative array with keys: artist, title, album, year, genre, comment, track_number
	 */
	public static function getTags(string $audioFilePath): array
	{
		$infos = self::getInfos($audioFilePath);
		if ($infos === null) {
			return [];
		}

		$tags = $infos['tags']['id3v2'] ?? $infos['tags']['id3v1'] ?? [];

		return [
			'artist' => $tags['artist'][0] ?? null,
			'title' => $tags['title'][0] ?? null,
			'album' => $tags['album'][0] ?? null,
			'year' => $tags['year'][0] ?? null,
			'genre' => $tags['genre'][0] ?? null,
			'comment' => $tags['comment'][0] ?? null,
			'track_number' => $tags['track_number'][0] ?? null,
		];
	}

	/**
	 * Check if an audio file is valid based on extension and format.
	 * Validates MP3 and WAV files using extension, MIME type, and format detection.
	 * @param string $filePath The path to the audio file to check
	 * @param string $clientOriginalName The original filename from the client
	 * @return bool True if the file is valid, false otherwise
	 */
	public static function checkFile(string $filePath, string $clientOriginalName): bool
	{
		return self::checkFileByType($filePath, $clientOriginalName, array_merge(self::MP3_EXTENSIONS, [self::WAV_EXTENSION]), null, [self::MP3_FORMAT, self::WAV_FORMAT]);
	}

	/**
	 * Check if an audio file is a valid MP3 file.
	 * Validates using extension, MIME type, and format detection.
	 * @param string $filePath The path to the audio file to check
	 * @param string $clientOriginalName The original filename from the client
	 * @return bool True if the file is a valid MP3, false otherwise
	 */
	public static function checkMp3File(string $filePath, string $clientOriginalName): bool
	{
		return self::checkFileByType($filePath, $clientOriginalName, self::MP3_EXTENSIONS, null, [self::MP3_FORMAT]);
	}

	/**
	 * Check if an audio file is a valid WAV file.
	 * Validates using extension, MIME type, and format detection.
	 * @param string $filePath The path to the audio file to check
	 * @param string $clientOriginalName The original filename from the client
	 * @return bool True if the file is a valid WAV, false otherwise
	 */
	public static function checkWavFile(string $filePath, string $clientOriginalName): bool
	{
		return self::checkFileByType($filePath, $clientOriginalName, [self::WAV_EXTENSION], null, [self::WAV_FORMAT]);
	}

	/**
	 * Internal method to validate audio files by extension, MIME type, and format.
	 * @param string $filePath The path to the audio file to check
	 * @param string $clientOriginalName The original filename from the client
	 * @param array|null $extensionsAllowed List of allowed file extensions
	 * @param array|null $mimeTypesAllowed List of allowed MIME types
	 * @param array|null $formatsAllowed List of allowed audio formats
	 * @return bool True if the file is valid, false otherwise
	 */
	private static function checkFileByType(string $filePath, string $clientOriginalName, ?array $extensionsAllowed, ?array $mimeTypesAllowed=null, ?array $formatsAllowed=null): bool
	{
		if (empty($filePath) || !\Osimatic\FileSystem\File::check($filePath, $clientOriginalName, $extensionsAllowed, $mimeTypesAllowed)) {
			return false;
		}

		if (!empty($formatsAllowed)) {
			$format = self::getFormat($filePath);
			if (!in_array($format, $formatsAllowed, true) || !self::isExtensionConsistentWithFormat($clientOriginalName, $format)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check that the extension of a file name matches the detected audio format (e.g. a WAV file named "audio.mp3" is inconsistent).
	 * @param string $clientOriginalName The original filename from the client
	 * @param string $format The detected audio format identifier (e.g., MP3_FORMAT, WAV_FORMAT)
	 * @return bool True if the extension belongs to the given format, false otherwise
	 */
	private static function isExtensionConsistentWithFormat(string $clientOriginalName, string $format): bool
	{
		// The WebM format identifier differs from its key in getExtensionsAndMimeTypes()
		$key = self::WEBM_FORMAT === $format ? 'weba' : $format;
		$extensions = self::getExtensionsAndMimeTypes()[$key][0] ?? [];

		return in_array(\Osimatic\FileSystem\File::getExtension($clientOriginalName, withDot: true), $extensions, true);
	}

	/**
	 * Normalize an ISMN by removing hyphens and spaces.
	 * @param string $ismn The ISMN number to normalize
	 * @return string The normalized ISMN number
	 */
	public static function normalizeIsmn(string $ismn): string
	{
		return str_replace(['-', ' '], '', trim($ismn));
	}

	/**
	 * Check if an ISMN (International Standard Music Number) is valid.
	 * Validates ISMN-13 format (13 digits starting with 979-0).
	 * @param string $ismn The ISMN number to check (with or without hyphens)
	 * @return bool True if the ISMN is valid, false otherwise
	 * @link https://en.wikipedia.org/wiki/International_Standard_Music_Number
	 */
	public static function isValidIsmn(string $ismn): bool
	{
		if (empty($ismn)) {
			return false;
		}

		$ismn = self::normalizeIsmn($ismn);

		// ISMN-13 must be 13 digits and start with 9790
		if (!preg_match('/^9790\d{9}$/', $ismn)) {
			return false;
		}

		// Validate check digit using ISBN-13 algorithm
		$checksum = 0;
		for ($i = 0; $i < 12; $i++) {
			$checksum += (int)$ismn[$i] * (($i % 2 === 0) ? 1 : 3);
		}
		$checkDigit = (10 - ($checksum % 10)) % 10;

		return (int)$ismn[12] === $checkDigit;
	}

	/**
	 * Get the MIME type associated with an audio file extension.
	 * @param string $extension The file extension (e.g., '.mp3', '.wav')
	 * @return string|null The MIME type if found, null otherwise
	 */
	public static function getMimeTypeFromExtension(string $extension): ?string
	{
		return \Osimatic\FileSystem\File::getMimeTypeFromExtension($extension, self::getExtensionsAndMimeTypes());
	}

	/**
	 * Get the file extension associated with an audio MIME type.
	 * @param string $mimeType The MIME type (e.g., 'audio/mpeg', 'audio/x-wav')
	 * @return string|null The file extension if found, null otherwise
	 */
	public static function getExtensionFromMimeType(string $mimeType): ?string
	{
		return \Osimatic\FileSystem\File::getExtensionFromMimeType($mimeType, self::getExtensionsAndMimeTypes());
	}

	/**
	 * Internal method to get the lowercase file extension from an audio file path.
	 * @param string $audioFilePath The complete path to the audio file
	 * @return string The lowercase file extension
	 */
	private static function getExtension(string $audioFilePath): string
	{
		$infosFile = new \SplFileInfo($audioFilePath);
		return mb_strtolower($infosFile->getExtension());
	}

	/**
	 * Send an audio file to the client browser for download.
	 * No output should be performed before or after calling this function.
	 * @param string $filePath The complete path to the audio file
	 * @param string|null $fileName The name the audio file will have when the client downloads it, or null to use the current filename (default null)
	 */
	public static function output(string $filePath, ?string $fileName=null): void
	{
		//\Osimatic\FileSystem\File::output($filePath, $fileName);
		\Osimatic\FileSystem\File::download($filePath, $fileName);
	}

	/**
	 * Send an audio file to the client browser for download using an OutputFile object.
	 * No output should be performed before or after calling this function.
	 * @param OutputFile $file The OutputFile object containing file information
	 */
	public static function outputFile(OutputFile $file): void
	{
		\Osimatic\FileSystem\File::downloadFile($file);
	}

	/**
	 * Play an audio file by sending it to the client browser.
	 * Supports both direct transfer and streaming with range requests.
	 * @param string $audioFilePath The complete path to the audio file
	 * @param bool $asStream Whether to use streaming mode with range requests support (default false)
	 */
	public static function play(string $audioFilePath, bool $asStream=false): void
	{
		self::_play($audioFilePath, $asStream, true);
	}

	/**
	 * Play an audio file using streaming mode with range requests support.
	 * Allows seeking and partial content delivery for audio players.
	 * @param string $audioFilePath The complete path to the audio file
	 */
	public static function playStream(string $audioFilePath): void
	{
		self::_playStream($audioFilePath, true);
	}

	/**
	 * Get an HTTP Response object for playing an audio file.
	 * Useful for integrating with frameworks that expect Response objects.
	 * @param string $audioFilePath The complete path to the audio file
	 * @param bool $asStream Whether to use streaming mode with range requests support (default false)
	 * @return Response The Symfony Response object containing the audio file
	 */
	public static function getHttpResponse(string $audioFilePath, bool $asStream=false): Response
	{
		return self::_play($audioFilePath, $asStream, false);
	}

	/**
	 * Internal method to handle audio playback either as direct output or as a Response object.
	 * Supports both full file transfer and streaming with range requests.
	 * Sets appropriate HTTP headers including Content-Type, Content-Length, and Cache-Control.
	 * @param string $audioFilePath The complete path to the audio file
	 * @param bool $asStream Whether to use streaming mode with range requests support
	 * @param bool $sendResponse Whether to send the response directly (true) or return a Response object (false)
	 * @return Response|null Response object if $sendResponse is false, null otherwise
	 */
	private static function _play(string $audioFilePath, bool $asStream=false, bool $sendResponse=true): ?Response
	{
		//20/02/23 : correction bug Maximum execution time
		set_time_limit(0);
		
		if ($asStream) {
			return self::_playStream($audioFilePath, $sendResponse);
		}

		if (!file_exists($audioFilePath)) {
			if (!$sendResponse) {
				return new Response('file_not_found', Response::HTTP_BAD_REQUEST);
			}
			return null;
		}

		$headers = [
			'Content-Disposition' => 'filename='.basename($audioFilePath),
			'Content-Length' => filesize($audioFilePath),
			'X-Pad' => 'avoid browser bug',
			'Cache-Control' => 'no-cache',
		];

		$extension = self::getExtension($audioFilePath);
		if (null !== ($mimeType = self::getMimeTypeFromExtension($extension))) {
			$headers['Content-Type'] = $mimeType;
		}
		if (null !== ($mimeType = Video::getMimeTypeFromExtension($extension))) {
			$headers['Content-Type'] = $mimeType;
		}

		if (!$sendResponse) {
			return new Response(file_get_contents($audioFilePath), Response::HTTP_OK, $headers);
		}

		foreach ($headers as $key => $value) {
			header($key.': '.$value);
		}
		readfile($audioFilePath);
		exit();
	}

	/**
	 * Send a 416 Range Not Satisfiable response for invalid range requests.
	 * @param array $headers The HTTP headers array
	 * @param int $start The start byte
	 * @param int $end The end byte
	 * @param int $size The total file size
	 * @param bool $sendResponse Whether to send the response directly (true) or return a Response object (false)
	 * @return Response|null Response object if $sendResponse is false, null otherwise (will exit if sendResponse is true)
	 */
	private static function sendRangeNotSatisfiable(array $headers, int $start, int $end, int $size, bool $sendResponse): ?Response
	{
		$headers['Content-Range'] = 'bytes '.$start.'-'.$end.'/'.$size;

		if (!$sendResponse) {
			return new Response(null, Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, $headers);
		}

		foreach ($headers as $key => $value) {
			header($key.': '.$value);
		}
		header('HTTP/1.1 416 Requested Range Not Satisfiable');
		exit();
	}

	/**
	 * Internal method to stream an audio file with support for HTTP range requests.
	 * Implements RFC 7233 partial content delivery for seeking and progressive download.
	 * Supports byte-range requests with Accept-Ranges, Content-Range, and 206 Partial Content responses.
	 * Handles invalid range requests with 416 Range Not Satisfiable responses.
	 * @param string $audioFilePath The complete path to the audio file
	 * @param bool $sendResponse Whether to send the response directly (true) or return a Response object (false)
	 * @return Response|null Response object if $sendResponse is false, null otherwise
	 */
	private static function _playStream(string $audioFilePath, bool $sendResponse=true): ?Response
	{
		if (!file_exists($audioFilePath) || false === ($fp = @fopen($audioFilePath, 'rb'))) {
			if (!$sendResponse) {
				return new Response('file_not_found', Response::HTTP_BAD_REQUEST);
			}
			return null;
		}

		$headers = [];
		$size 	= filesize($audioFilePath); 	// File size
		$length = $size;						// Content length
		$start 	= 0;							// Start byte
		$end 	= $size - 1;					// End byte

		$extension = self::getExtension($audioFilePath);
		if (null !== ($mimeType = self::getMimeTypeFromExtension($extension))) {
			$headers['Content-Type'] = $mimeType;
		}
		else if (null !== ($mimeType = Video::getMimeTypeFromExtension($extension))) {
			$headers['Content-Type'] = $mimeType;
		}

		//header('Accept-Ranges: bytes');
		$headers['Accept-Ranges'] = '0-'.$length;

		$isPartialContent = isset($_SERVER['HTTP_RANGE']);
		if ($isPartialContent) {
			$c_start = $start;
			$c_end = $end;
			[, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2);
			if (str_contains($range, ',')) {
				return self::sendRangeNotSatisfiable($headers, $start, $end, $size, $sendResponse);
			}
			if ($range === '-') {
				$c_start = $size - substr($range, 1);
			}
			else {
				$range = explode('-', $range);
				$c_start = $range[0];
				$c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}
			$c_end = ($c_end > $end) ? $end : $c_end;
			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
				return self::sendRangeNotSatisfiable($headers, $start, $end, $size, $sendResponse);
			}
			$start = $c_start;
			$end = $c_end;
			$length = $end - $start + 1;
			fseek($fp, $start);
			$headers['Content-Range'] = 'bytes '.$start.'-'.$end.'/'.$size;
		}

		$headers['Content-Length'] = $length;

		if ($sendResponse) {
			foreach ($headers as $key => $value) {
				header($key.': '.$value);
			}
			if ($isPartialContent) {
				header('HTTP/1.1 206 Partial Content');
			}
		}

		$data = '';
		$buffer = self::STREAM_BUFFER_SIZE;
		while(!feof($fp) && ($p = ftell($fp)) <= $end) {
			if ($p + $buffer > $end) {
				$buffer = $end - $p + 1;
			}
			set_time_limit(0);

			if (!$sendResponse) {
				$data .= fread($fp, $buffer);
				continue;
			}

			echo fread($fp, $buffer);
			flush();
		}
		fclose($fp);

		if (!$sendResponse) {
			return new Response($data, $isPartialContent ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK, $headers);
		}

		exit();
	}

	// ========== DEPRECATED METHODS (Backward Compatibility) ==========

	/**
	 * @deprecated Use isValidIsmn() instead
	 */
	public static function checkIsmn(string $ismn): bool
	{
		return self::isValidIsmn($ismn);
	}

}