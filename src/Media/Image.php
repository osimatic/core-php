<?php

namespace Osimatic\Media;

use Symfony\Component\HttpFoundation\Response;

/**
 * Class Image
 * Provides utilities for handling image files including validation, MIME type detection, EXIF data extraction, and HTTP output.
 * Supports multiple image formats: JPG, PNG, GIF, SVG, BMP, WebP, and TIFF.
 */
class Image
{
	public const string JPG_EXTENSION 		= '.jpg';
	public const array JPG_EXTENSIONS 		= [self::JPG_EXTENSION, '.jpeg', '.jpe'];
	public const array JPG_MIME_TYPES 		= ['image/jpeg'];

	public const string PNG_EXTENSION 		= '.png';
	public const array PNG_MIME_TYPES 		= ['image/png'];

	public const string GIF_EXTENSION 		= '.gif';
	public const array GIF_MIME_TYPES 		= ['image/gif'];

	public const string SVG_EXTENSION 		= '.svg';
	public const array SVG_MIME_TYPES 		= ['image/svg+xml'];

	public const string BMP_EXTENSION 		= '.bmp';
	public const array BMP_MIME_TYPES 		= ['image/bmp'];

	public const string WEBP_EXTENSION 		= '.webp';
	public const array WEBP_MIME_TYPES 		= ['image/webp'];

	public const string TIFF_EXTENSION 		= '.tiff';
	public const array TIFF_EXTENSIONS 		= [self::TIFF_EXTENSION, '.tif'];
	public const array TIFF_MIME_TYPES 		= ['image/tiff'];

	/**
	 * Get all supported image extensions and their associated MIME types.
	 * @return array Associative array mapping format names to arrays of extensions and MIME types
	 */
	public static function getExtensionsAndMimeTypes(): array
	{
		return [
			'jpg' => [self::JPG_EXTENSIONS, self::JPG_MIME_TYPES],
			'png' => [[self::PNG_EXTENSION], self::PNG_MIME_TYPES],
			'gif' => [[self::GIF_EXTENSION], self::GIF_MIME_TYPES],
			'svg' => [[self::SVG_EXTENSION], self::SVG_MIME_TYPES],
			'bmp' => [[self::BMP_EXTENSION], self::BMP_MIME_TYPES],
			'webp' => [[self::WEBP_EXTENSION], self::WEBP_MIME_TYPES],
			'tiff' => [self::TIFF_EXTENSIONS, self::TIFF_MIME_TYPES],
		];
	}

	// ========== Validation ==========

	/**
	 * Check if an image file is valid based on extension and MIME type.
	 * Supports JPG, PNG, GIF, SVG, BMP, WebP, and TIFF formats.
	 * @param string $filePath The path to the image file to check
	 * @param string $clientOriginalName The original filename from the client
	 * @return bool True if the file is valid, false otherwise
	 */
	public static function checkFile(string $filePath, string $clientOriginalName): bool
	{
		return \Osimatic\FileSystem\File::check($filePath, $clientOriginalName, array_merge(self::JPG_EXTENSIONS, [self::PNG_EXTENSION], [self::GIF_EXTENSION], [self::SVG_EXTENSION], [self::BMP_EXTENSION], [self::WEBP_EXTENSION], self::TIFF_EXTENSIONS), array_merge(self::JPG_MIME_TYPES, self::PNG_MIME_TYPES, self::GIF_MIME_TYPES, self::SVG_MIME_TYPES, self::BMP_MIME_TYPES, self::WEBP_MIME_TYPES, self::TIFF_MIME_TYPES));
	}

	/**
	 * Check if an image file is a valid JPG file.
	 * @param string $filePath The path to the image file to check
	 * @param string $clientOriginalName The original filename from the client
	 * @return bool True if the file is a valid JPG, false otherwise
	 */
	public static function checkJpgFile(string $filePath, string $clientOriginalName): bool
	{
		return \Osimatic\FileSystem\File::check($filePath, $clientOriginalName, self::JPG_EXTENSIONS, self::JPG_MIME_TYPES);
	}

	/**
	 * Check if an image file is a valid PNG file.
	 * @param string $filePath The path to the image file to check
	 * @param string $clientOriginalName The original filename from the client
	 * @return bool True if the file is a valid PNG, false otherwise
	 */
	public static function checkPngFile(string $filePath, string $clientOriginalName): bool
	{
		return \Osimatic\FileSystem\File::check($filePath, $clientOriginalName, [self::PNG_EXTENSION], self::PNG_MIME_TYPES);
	}

	/**
	 * Check if an image file is a valid GIF file.
	 * @param string $filePath The path to the image file to check
	 * @param string $clientOriginalName The original filename from the client
	 * @return bool True if the file is a valid GIF, false otherwise
	 */
	public static function checkGifFile(string $filePath, string $clientOriginalName): bool
	{
		return \Osimatic\FileSystem\File::check($filePath, $clientOriginalName, [self::GIF_EXTENSION], self::GIF_MIME_TYPES);
	}

	/**
	 * Get the MIME type associated with an image file extension.
	 * @param string $extension The file extension (e.g., '.jpg', '.png')
	 * @return string|null The MIME type if found, null otherwise
	 */
	public static function getMimeTypeFromExtension(string $extension): ?string
	{
		return \Osimatic\FileSystem\File::getMimeTypeFromExtension($extension, self::getExtensionsAndMimeTypes());
	}

	/**
	 * Get the file extension associated with an image MIME type.
	 * @param string $mimeType The MIME type (e.g., 'image/jpeg')
	 * @return string|null The file extension if found, null otherwise
	 */
	public static function getExtensionFromMimeType(string $mimeType): ?string
	{
		return \Osimatic\FileSystem\File::getExtensionFromMimeType($mimeType, self::getExtensionsAndMimeTypes());
	}

	// ========== Information Retrieval ==========

	/**
	 * Get the width of an image in pixels.
	 * @param string $imgPath The complete path to the image file
	 * @return int|null The width of the image in pixels, null if an error occurs while reading the file
	 */
	public static function getWidth(string $imgPath): ?int
	{
		if (($size = self::getImageSize($imgPath)) === false) {
			return null;
		}
		return $size[0];
	}

	/**
	 * Get the height of an image in pixels.
	 * @param string $imgPath The complete path to the image file
	 * @return int|null The height of the image in pixels, null if an error occurs while reading the file
	 */
	public static function getHeight(string $imgPath): ?int
	{
		if (($size = self::getImageSize($imgPath)) === false) {
			return null;
		}
		return $size[1];
	}

	/**
	 * Get the MIME type of an image file.
	 * @param string $imgPath The complete path to the image file
	 * @return string|null The MIME type of the image, null if an error occurs while reading the file
	 */
	public static function getMimeType(string $imgPath): ?string
	{
		if (($size = self::getImageSize($imgPath)) === false) {
			return null;
		}
		return $size['mime'];
	}

	/**
	 * Get image information (width, height, MIME type) in a single call.
	 * More efficient than calling getWidth(), getHeight(), and getMimeType() separately.
	 * @param string $imgPath The complete path to the image file
	 * @return array|null Associative array with keys 'width', 'height', and 'mime', null if an error occurs while reading the file
	 */
	public static function getImageInfo(string $imgPath): ?array
	{
		if (($size = self::getImageSize($imgPath)) === false) {
			return null;
		}
		return [
			'width' => $size[0],
			'height' => $size[1],
			'mime' => $size['mime']
		];
	}

	/**
	 * Generate an ETag for an image file based on file modification time and size.
	 * More efficient than MD5 hashing the entire file content.
	 * @param string $imgPath The complete path to the image file
	 * @return string|null The ETag hash, null if an error occurs while reading the file
	 */
	public static function getEtag(string $imgPath): ?string
	{
		if (!file_exists($imgPath)) {
			return null;
		}
		$mtime = filemtime($imgPath);
		$size = filesize($imgPath);
		if ($mtime === false || $size === false) {
			return null;
		}
		return md5($mtime . '-' . $size);
	}

	/**
	 * Get the last modified date of an image file formatted for HTTP headers.
	 * @param string $imgPath The complete path to the image file
	 * @return string|null The formatted date string (e.g., "Mon, 15 Jan 2026 10:30:00 GMT"), null if an error occurs
	 */
	public static function getLastModifiedString(string $imgPath): ?string
	{
		if (!is_file($imgPath) || false === ($lastModifTimestamp = filemtime($imgPath))) {
			return null;
		}
		if (!($lastModifFormattedDateTime = gmdate('D, d M Y H:i:s', $lastModifTimestamp))) {
			return null;
		}
		return $lastModifFormattedDateTime . ' GMT';
	}

	/**
	 * Get the timestamp when a photo was taken from EXIF data.
	 * First tries to read DateTimeOriginal, then falls back to DateTime.
	 * @param string $photoPath The complete path to the photo file
	 * @return int|null The Unix timestamp when the photo was taken, null if EXIF data is not available
	 */
	public static function getPhotoTimestamp(string $photoPath): ?int
	{
		if (($exifDataPhoto = self::readExifData($photoPath)) !== false) {
			if (isset($exifDataPhoto['EXIF']['DateTimeOriginal'])) {
				return strtotime($exifDataPhoto['EXIF']['DateTimeOriginal']);
			}
			if (isset($exifDataPhoto['IFD0']['DateTime'])) {
				return strtotime($exifDataPhoto['IFD0']['DateTime']);
			}
		}
		return null;
	}

	/**
	 * Get the camera manufacturer name from EXIF data.
	 * @param string $photoPath The complete path to the photo file to analyze
	 * @return string|null The camera manufacturer name, null if an error occurs or if the information is not available
	 */
	public static function getCameraMake(string $photoPath): ?string
	{
		if (($exifDataPhoto = self::readExifData($photoPath)) !== false) {
			return $exifDataPhoto['IFD0']['Make'] ?? null;
		}
		return null;
	}

	/**
	 * Get the camera model name from EXIF data.
	 * @param string $photoPath The complete path to the photo file to analyze
	 * @return string|null The camera model name, null if an error occurs or if the information is not available
	 */
	public static function getCameraModel(string $photoPath): ?string
	{
		if (($exifDataPhoto = self::readExifData($photoPath)) !== false) {
			return $exifDataPhoto['IFD0']['Model'] ?? null;
		}
		return null;
	}

	/**
	 * Read all EXIF data from a photo file.
	 * Requires the EXIF PHP extension to be enabled.
	 * @param string $photoPath The complete path to the photo file
	 * @return array|null An array containing all EXIF data sections, null if the EXIF extension is not available or if reading fails
	 */
	public static function readExifData(string $photoPath): ?array
	{
		if (!function_exists('exif_read_data')) {
			return null;
		}

		$exif = @exif_read_data($photoPath, null, true);
		return $exif !== false ? $exif : null;
	}

	/**
	 * Get common photo information from EXIF data in a single call.
	 * More efficient than calling individual methods when multiple EXIF values are needed.
	 * @param string $photoPath The complete path to the photo file
	 * @return array|null Associative array with keys: timestamp, cameraMake, cameraModel, iso, aperture, shutterSpeed, focalLength. Null if EXIF data is not available.
	 */
	public static function getPhotoInfo(string $photoPath): ?array
	{
		if (($exifData = self::readExifData($photoPath)) === null) {
			return null;
		}

		$timestamp = null;
		if (isset($exifData['EXIF']['DateTimeOriginal'])) {
			$timestamp = strtotime($exifData['EXIF']['DateTimeOriginal']);
		} elseif (isset($exifData['IFD0']['DateTime'])) {
			$timestamp = strtotime($exifData['IFD0']['DateTime']);
		}

		return [
			'timestamp' => $timestamp,
			'cameraMake' => $exifData['IFD0']['Make'] ?? null,
			'cameraModel' => $exifData['IFD0']['Model'] ?? null,
			'iso' => $exifData['EXIF']['ISOSpeedRatings'] ?? null,
			'aperture' => $exifData['COMPUTED']['ApertureFNumber'] ?? null,
			'shutterSpeed' => $exifData['EXIF']['ExposureTime'] ?? null,
			'focalLength' => $exifData['EXIF']['FocalLength'] ?? null,
		];
	}

	/**
	 * Get the ISO speed rating from EXIF data.
	 * @param string $photoPath The complete path to the photo file
	 * @return int|null The ISO speed rating, null if an error occurs or if the information is not available
	 */
	public static function getIso(string $photoPath): ?int
	{
		if (($exifData = self::readExifData($photoPath)) !== null) {
			return $exifData['EXIF']['ISOSpeedRatings'] ?? null;
		}
		return null;
	}

	/**
	 * Get the aperture f-number from EXIF data.
	 * @param string $photoPath The complete path to the photo file
	 * @return string|null The aperture f-number (e.g., "f/2.8"), null if an error occurs or if the information is not available
	 */
	public static function getAperture(string $photoPath): ?string
	{
		if (($exifData = self::readExifData($photoPath)) !== null) {
			return $exifData['COMPUTED']['ApertureFNumber'] ?? null;
		}
		return null;
	}

	/**
	 * Get the shutter speed from EXIF data.
	 * @param string $photoPath The complete path to the photo file
	 * @return string|null The shutter speed (e.g., "1/250"), null if an error occurs or if the information is not available
	 */
	public static function getShutterSpeed(string $photoPath): ?string
	{
		if (($exifData = self::readExifData($photoPath)) !== null) {
			return $exifData['EXIF']['ExposureTime'] ?? null;
		}
		return null;
	}

	/**
	 * Get the focal length from EXIF data.
	 * @param string $photoPath The complete path to the photo file
	 * @return string|null The focal length (e.g., "50/1" for 50mm), null if an error occurs or if the information is not available
	 */
	public static function getFocalLength(string $photoPath): ?string
	{
		if (($exifData = self::readExifData($photoPath)) !== null) {
			return $exifData['EXIF']['FocalLength'] ?? null;
		}
		return null;
	}

	/**
	 * Get GPS coordinates from EXIF data.
	 * @param string $photoPath The complete path to the photo file
	 * @return array|null Associative array with keys 'latitude' and 'longitude' as decimal degrees, null if GPS data is not available
	 */
	public static function getGpsCoordinates(string $photoPath): ?array
	{
		if (($exifData = self::readExifData($photoPath)) === null) {
			return null;
		}

		if (!isset($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef'], $exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef'])) {
			return null;
		}

		$latitude = \Osimatic\Location\GeographicCoordinates::gpsToDecimal($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef']);
		$longitude = \Osimatic\Location\GeographicCoordinates::gpsToDecimal($exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef']);

		return [
			'latitude' => $latitude,
			'longitude' => $longitude,
		];
	}

	/**
	 * Reads the image information with getimagesize() only when the file exists, to avoid PHP warnings on missing files.
	 * @param string $imgPath The complete path to the image file
	 * @return array|false The result of getimagesize(), or false if the file does not exist or cannot be read as an image
	 * @link https://www.php.net/manual/en/function.getimagesize.php
	 */
	private static function getImageSize(string $imgPath): array|false
	{
		if (!is_file($imgPath)) {
			return false;
		}
		return getimagesize($imgPath);
	}

	// ========== Image Output ==========

	/**
	 * Output an image file to the browser with appropriate headers.
	 * Supports HTTP caching with ETag and Last-Modified headers.
	 * @param string $file The complete path to the image file
	 * @param bool $withCache Whether to use HTTP caching (default true)
	 */
	public static function output(string $file, bool $withCache=true): void
	{
		self::_output($file, $withCache, true);
	}

	/**
	 * Get a Symfony HTTP Response object for an image file.
	 * Supports HTTP caching with ETag and Last-Modified headers.
	 * @param string $file The complete path to the image file
	 * @param bool $withCache Whether to use HTTP caching (default true)
	 * @return Response The Symfony Response object with appropriate headers and content
	 */
	public static function getHttpResponse(string $file, bool $withCache=true): Response
	{
		return self::_output($file, $withCache, false);
	}

	/**
	 * Internal method to handle image output either as direct output or as a Response object.
	 * Implements HTTP caching with ETag and Last-Modified headers and returns 304 Not Modified when appropriate.
	 * @param string $file The complete path to the image file
	 * @param bool $withCache Whether to use HTTP caching (default true)
	 * @param bool $sendResponse Whether to send the response directly (true) or return a Response object (false)
	 * @return Response|null Response object if $sendResponse is false, null otherwise
	 */
	private static function _output(string $file, bool $withCache=true, bool $sendResponse=true): ?Response
	{
		if (!file_exists($file)) {
			if (!$sendResponse) {
				return new Response('file_not_found', Response::HTTP_BAD_REQUEST);
			}
			return null;
		}

		$lastModifiedString = self::getLastModifiedString($file);
		$mime 				= self::getMimeType($file);
		$etag 				= self::getEtag($file);

		$headers = [
			'Last-Modified' => $lastModifiedString,
			'ETag' => '"'.$etag.'"'
		];

		$outputImage = true;

		if ($withCache) {
			$outputImage = false;

			$ifNoneMatch = false;
			if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
				$ifNoneMatch = stripslashes($_SERVER['HTTP_IF_NONE_MATCH']);
			}

			$ifModifiedSince = false;
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				$ifModifiedSince = stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']);
			}

			if (!$ifModifiedSince && !$ifNoneMatch) {
				$outputImage = true;
			}

			if ($ifNoneMatch && $ifNoneMatch != $etag && $ifNoneMatch != '"' . $etag . '"') {
				// etag is there but doesn't match
				$outputImage = true;
			}

			if ($ifModifiedSince && $ifModifiedSince != $lastModifiedString) {
				// if-modified-since is there but doesn't match
				$outputImage = true;
			}
		}

		if (!$outputImage) {
			// Nothing has changed since their last request - serve a 304 and exit
			if (!$sendResponse) {
				return new Response(null, Response::HTTP_NOT_MODIFIED);
			}

			header('HTTP/1.1 304 Not Modified');
			exit();
		}

		$headers['Content-Type'] = $mime;
		$headers['Content-Length'] = filesize($file);

		if (!$sendResponse) {
			if (($data = file_get_contents($file)) === false) {
				return new Response('file_read_error', Response::HTTP_INTERNAL_SERVER_ERROR);
			}
			return new Response($data, Response::HTTP_OK, $headers);
		}

		foreach ($headers as $key => $value) {
			header($key.': '.$value);
		}
		readfile($file);
		exit();
	}
}