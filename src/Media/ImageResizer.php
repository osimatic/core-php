<?php

namespace Osimatic\Media;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use InvalidArgumentException;

/**
 * Class ImageResizer
 * Provides intelligent image resizing capabilities with automatic sharpening, cropping, and transparency handling.
 * Supports JPG, PNG, GIF, and WebP formats with optimized quality settings.
 * Features include:
 * - Multiple crop positions (center, top, bottom, left, right, corners)
 * - Configurable sharpening intensity
 * - Multiple resize modes (fit, fill, stretch, cover)
 * - Separate output file support
 * - Parameter validation
 * Based on Smart Image Resizer by Joe Lencioni (http://shiftingpixel.com)
 */
class ImageResizer
{
	private const int MAX_DIMENSION_FALLBACK = 99999999;
	private const int DEFAULT_QUALITY = 90;
	private const int SHARPENING_CONSTANT_A = 52;
	private const float SHARPENING_CONSTANT_B = -0.27810650887573124;
	private const float SHARPENING_CONSTANT_C = 0.00047337278106508946;
	private const float SHARPENING_SCALE_FACTOR = 750.0;

	/**
	 * Constructor.
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
	 * Validates the quality parameter.
	 * @param int $quality Quality value to validate
	 * @throws InvalidArgumentException If quality is not between 0 and 100
	 */
	private function validateQuality(int $quality): void
	{
		if ($quality < 0 || $quality > 100) {
			throw new InvalidArgumentException("Quality must be between 0 and 100, got: $quality");
		}
	}

	/**
	 * Validates the color parameter (hex color format).
	 * @param string $color Color value to validate
	 * @throws InvalidArgumentException If color is not a valid hex color
	 */
	private function validateColor(string $color): void
	{
		if (!preg_match('/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color)) {
			throw new InvalidArgumentException("Invalid hex color format: $color. Expected format: #RGB or #RRGGBB (with or without #)");
		}
	}

	/**
	 * Validates the ratio parameter (format: width:height).
	 * @param string $ratio Ratio value to validate
	 * @throws InvalidArgumentException If ratio format is invalid or contains zero/negative numbers
	 */
	private function validateRatio(string $ratio): void
	{
		if (!preg_match('/^\d+:\d+$/', $ratio)) {
			throw new InvalidArgumentException("Invalid ratio format: $ratio. Expected format: width:height (e.g., 16:9 or 1:1)");
		}

		$parts = explode(':', $ratio);
		if ((int)$parts[0] <= 0 || (int)$parts[1] <= 0) {
			throw new InvalidArgumentException("Ratio values must be positive integers: $ratio");
		}
	}

	/**
	 * Resizes images with intelligent sharpening, aspect ratio cropping, and transparency handling.
	 * Supports JPG, PNG, GIF, and WebP formats with configurable sharpening, crop positioning, and resize modes.
	 * Can save to a separate output file or replace the original.
	 * @see http://veryraw.com/history/2005/03/image-resizing-with-php/
	 * @param string $imagePath The path to the image to resize
	 * @param int $maxWidth Maximum width of final image in pixels, 0 to keep original width (default 0)
	 * @param int $maxHeight Maximum height of final image in pixels, 0 to keep original height (default 0)
	 * @param int|null $quality Quality of output image, between 0 and 100 (default null, will use 90)
	 * @param string|null $color Background hex color for filling transparent areas (e.g., #FF0000 or FFF), null to preserve transparency (default null)
	 * @param string|null $ratio Ratio of width to height to crop final image (e.g., 1:1 or 16:9), null to skip cropping (default null)
	 * @param string|null $outputPath Path where to save the resized image, null to replace original (default null)
	 * @param ImageSharpeningIntensity $sharpeningIntensity Intensity of sharpening to apply (default: MEDIUM)
	 * @param ImageCropPosition $cropPosition Position for ratio-based cropping (default: CENTER)
	 * @param ImageResizeMode $resizeMode Mode for resizing (fit, fill, stretch, cover) (default: FIT)
	 * @return bool True if the image was resized successfully, false otherwise
	 * @throws InvalidArgumentException If quality, color, or ratio parameters are invalid
	 */
	public function resize(
		string $imagePath,
		int $maxWidth = 0,
		int $maxHeight = 0,
		?int $quality = null,
		?string $color = null,
		?string $ratio = null,
		?string $outputPath = null,
		ImageSharpeningIntensity $sharpeningIntensity = ImageSharpeningIntensity::MEDIUM,
		ImageCropPosition $cropPosition = ImageCropPosition::CENTER,
		ImageResizeMode $resizeMode = ImageResizeMode::FIT
	): bool
	{
		$this->logger->info('Image resized: '.$imagePath);

		// Validate parameters
		if ($quality !== null) {
			$this->validateQuality($quality);
		}
		if ($color !== null) {
			$this->validateColor($color);
		}
		if ($ratio !== null) {
			$this->validateRatio($ratio);
		}

		$size = is_file($imagePath) ? getimagesize($imagePath) : false;
		if ($size === false) {
			$this->logger->error('Image does not exist.');
			return false;
		}

		$mime = Image::getMimeType($imagePath);

		if (!str_starts_with($mime, 'image/')) {
			$this->logger->error('Image format not supported.');
			return false;
		}

		// Determine output path (use input path if not specified)
		if ($outputPath === null) {
			$outputPath = $imagePath;
		}

		[$width, $height] = $size;

		// If either a max width or max height are not specified, we default to something large so the unspecified dimension isn't a constraint on our resized image.
		// If neither are specified but the color is, we aren't going to be resizing at all, just coloring.
		if (0 === $maxWidth && 0 !== $maxHeight) {
			$maxWidth	= self::MAX_DIMENSION_FALLBACK;
		}
		elseif (0 !== $maxWidth && 0 === $maxHeight) {
			$maxHeight	= self::MAX_DIMENSION_FALLBACK;
		}
		// elseif ($color && !$maxWidth && !$maxHeight) {
		elseif (0 === $maxWidth && 0 === $maxHeight) {
			$maxWidth	= $width;
			$maxHeight	= $height;
		}

		// If we don't have a max width or max height, OR the image is smaller than both we do not want to resize it, so we simply output the original image and exit
		// if ((!$maxWidth && !$maxHeight) || (!$color && $maxWidth >= $width && $maxHeight >= $height)) {
		if (!$color && !$ratio && $maxWidth >= $width && $maxHeight >= $height) {
			$this->logger->error('No resize needed.');
			// possibly display the image
			return true;
		}

		// Ratio cropping
		$offsetX	= 0;
		$offsetY	= 0;

		if ($ratio !== null) {
			$cropRatio = explode(':', $ratio);
			if (count($cropRatio) !== 2) {
				$this->logger->error('Invalid ratio.');
				return false;
			}

			$ratioComputed		= $width / $height;
			$cropRatioComputed	= (float) $cropRatio[0] / (float) $cropRatio[1];

			if ($ratioComputed < $cropRatioComputed) {
				// Image is too tall so we will crop the top and bottom
				$origHeight	= $height;
				$height		= $width / $cropRatioComputed;

				// Calculate vertical offset based on crop position
				$offsetY = match($cropPosition) {
					ImageCropPosition::TOP, ImageCropPosition::TOP_LEFT, ImageCropPosition::TOP_RIGHT => 0,
					ImageCropPosition::BOTTOM, ImageCropPosition::BOTTOM_LEFT, ImageCropPosition::BOTTOM_RIGHT => $origHeight - $height,
					default => ($origHeight - $height) / 2, // CENTER and others
				};
			}
			else if ($ratioComputed > $cropRatioComputed) {
				// Image is too wide so we will crop off the left and right sides
				$origWidth	= $width;
				$width		= $height * $cropRatioComputed;

				// Calculate horizontal offset based on crop position
				$offsetX = match($cropPosition) {
					ImageCropPosition::LEFT, ImageCropPosition::TOP_LEFT, ImageCropPosition::BOTTOM_LEFT => 0,
					ImageCropPosition::RIGHT, ImageCropPosition::TOP_RIGHT, ImageCropPosition::BOTTOM_RIGHT => $origWidth - $width,
					default => ($origWidth - $width) / 2, // CENTER and others
				};
			}
		}

		// Calculate target dimensions based on resize mode
		$xRatio		= $maxWidth / $width;
		$yRatio		= $maxHeight / $height;

		switch ($resizeMode) {
			case ImageResizeMode::STRETCH:
				// Stretch mode: Ignore aspect ratio, fill exact dimensions
				$tnWidth	= $maxWidth;
				$tnHeight	= $maxHeight;
				break;

			case ImageResizeMode::FILL:
			case ImageResizeMode::COVER:
				// Fill/Cover mode: Scale to cover entire area, may crop edges
				// Use the larger ratio to ensure the entire area is covered
				if ($xRatio > $yRatio) {
					$tnWidth	= $maxWidth;
					$tnHeight	= ceil($xRatio * $height);
				}
				else {
					$tnWidth	= ceil($yRatio * $width);
					$tnHeight	= $maxHeight;
				}
				break;

			case ImageResizeMode::FIT:
			default:
				// Fit mode: Scale to fit within dimensions, preserve aspect ratio
				// Use the smaller ratio to ensure the image fits entirely
				if ($xRatio * $height < $maxHeight) {
					// Resize the image based on width
					$tnHeight	= ceil($xRatio * $height);
					$tnWidth	= $maxWidth;
				}
				else {
					// Resize the image based on height
					$tnWidth	= ceil($yRatio * $width);
					$tnHeight	= $maxHeight;
				}
				break;
		}

		// Determine the quality of the output image
		if ($quality === null) {
			$quality = self::DEFAULT_QUALITY;
		}

		// Set up a blank canvas for our resized image (destination)
		$dst = imagecreatetruecolor($tnWidth, $tnHeight);

		// Set up the appropriate image handling functions based on the original image's mime type
		switch ($size['mime']) {
			case 'image/gif':
				// We will be converting GIFs to PNGs to avoid transparency issues when resizing GIFs
				// This is maybe not the ideal solution, but IE6 can suck it
				$creationFunction	= 'ImageCreateFromGif';
				$outputFunction		= 'ImagePng';
				$doSharpen			= false;
				// We are converting the GIF to a PNG and PNG needs a compression level of 0 (no compression) through 9
				$quality			= (int) round(10 - ($quality / 10));
				break;

			case 'image/x-png':
			case 'image/png':
				$creationFunction	= 'ImageCreateFromPng';
				$outputFunction		= 'ImagePng';
				$doSharpen			= false;
				// PNG needs a compression level of 0 (no compression) through 9
				$quality			= (int) round(10 - ($quality / 10));
				break;

			case 'image/webp':
				$creationFunction	= 'ImageCreateFromWebp';
				$outputFunction		= 'ImageWebp';
				$doSharpen			= true;
				// WebP quality from 0 (worst) to 100 (best)
				break;

			default:
				$creationFunction	= 'ImageCreateFromJpeg';
				$outputFunction	 	= 'ImageJpeg';
				$doSharpen			= true;
				break;
		}

		// Read in the original image
		$src = $creationFunction($imagePath);

		if (in_array($size['mime'], ['image/gif', 'image/png', 'image/webp'])) {
			if (!$color) {
				// If this is a GIF or a PNG, we need to set up transparency
				imagealphablending($dst, false);
				imagesavealpha($dst, true);
			}
			else {
				// Fill the background with the specified color for matting purposes
				if ($color[0] === '#') {
					$color = substr($color, 1);
				}

				$background	= false;

				if (strlen($color) === 6) {
					$background	= imagecolorallocate($dst, hexdec($color[0].$color[1]), hexdec($color[2].$color[3]), hexdec($color[4].$color[5]));
				}
				else if (strlen($color) === 3) {
					$background	= imagecolorallocate($dst, hexdec($color[0].$color[0]), hexdec($color[1].$color[1]), hexdec($color[2].$color[2]));
				}

				if ($background) {
					imagefill($dst, 0, 0, $background);
				}
			}
		}

		// Resample the original image into the resized canvas we set up earlier
		ImageCopyResampled($dst, $src, 0, 0, (int) $offsetX, (int) $offsetY, (int) $tnWidth, (int) $tnHeight, (int) $width, (int) $height);

		if ($doSharpen && $sharpeningIntensity->shouldApply()) {
			// Sharpen the image based on two things:
			//	(1) the difference between the original size and the final size
			//	(2) the final size
			// Code from Ryan Rud (http://adryrun.com)
			$final	= $tnWidth * (self::SHARPENING_SCALE_FACTOR / $width);
			$result = self::SHARPENING_CONSTANT_A + self::SHARPENING_CONSTANT_B * $final + self::SHARPENING_CONSTANT_C * $final * $final;

			// Apply sharpening intensity multiplier
			$sharpness	= max(round($result * $sharpeningIntensity->getMultiplier()), 0);

			$sharpenMatrix	= array(
				array(-1, -2, -1),
				array(-2, $sharpness + 12, -2),
				array(-1, -2, -1)
			);
			$divisor		= $sharpness;
			$offset			= 0;
			imageconvolution($dst, $sharpenMatrix, $divisor, $offset);
		}

		// Write the resized image to the output path
		$outputFunction($dst, $outputPath, $quality);

		// Clean up the memory
		ImageDestroy($src);
		ImageDestroy($dst);

		return true;
	}
}