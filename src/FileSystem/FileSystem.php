<?php

namespace Osimatic\FileSystem;

/**
 * Utility class for file system operations including path manipulation and directory management.
 * Provides helper methods for:
 * - Path formatting and normalization (handling Unix/Windows separators)
 * - Directory creation with recursive support
 * - File initialization (cleanup and directory preparation)
 */
class FileSystem
{
	// ========== Path Manipulation ==========

	/**
	 * Formats a file path by normalizing separators and removing redundant elements.
	 * - Removes unnecessary '//' and './' elements
	 * - Replaces '\' and '/' with the PHP DIRECTORY_SEPARATOR constant
	 * - Preserves UNC paths (\\server\share) on Windows
	 * @param string $filePath The path to format (absolute or relative)
	 * @return string The formatted path
	 */
	public static function formatPath(string $filePath): string
	{
		// Remove unnecessary './' elements
		$filePath = preg_replace('#\/\.\/#', DIRECTORY_SEPARATOR, $filePath);

		$isUnc = (str_starts_with($filePath, '\\\\'));
		if ($isUnc) {
			$filePath = ltrim($filePath, '\\');
		}

		// Remove unnecessary '//' and \\ elements
		$filePath = preg_replace('#\/(\/)*\/#', DIRECTORY_SEPARATOR, $filePath);
		$filePath = preg_replace('#\\\\(\\\\)*\\\\#m', DIRECTORY_SEPARATOR, $filePath);

		// Replace '\' with DIRECTORY_SEPARATOR
		$filePath = preg_replace('#\\\\#m', DIRECTORY_SEPARATOR, $filePath);
		$filePath = preg_replace('#\/#', DIRECTORY_SEPARATOR, $filePath);

		return ($isUnc?'\\\\':'').$filePath;
	}

	/**
	 * Strips surrounding single and double quotes from a path.
	 * Useful when a binary path is configured with shell-style quotes (e.g. `"C:\Program Files\tool.exe"`)
	 * that would cause is_executable() checks to fail.
	 * @param string $path The path to strip quotes from
	 * @return string The path without surrounding quotes
	 */
	public static function stripQuotes(string $path): string
	{
		return trim($path, '\'"');
	}

	/**
	 * Returns the directory path without the filename.
	 * Ensures the result always ends with a directory separator.
	 * @param string $filePath The full file path
	 * @return string The directory path with trailing separator
	 */
	public static function dirname(string $filePath): string
	{
		$dirPath = self::formatPath($filePath);
		// If path already ends with a separator, it's a directory, return as is
		if (substr($dirPath, -1) === DIRECTORY_SEPARATOR) {
			return $dirPath;
		}
		// Otherwise it's a file, return the parent directory
		return dirname($dirPath).DIRECTORY_SEPARATOR;
	}

	// ========== Directory Management ==========

	/**
	 * Creates all directories in the path recursively if they don't exist.
	 * Creates all parent directories from root to the final directory.
	 * @param string $filePath The complete path to the directory or file (from disk root)
	 * @return bool True if directories were created successfully, false on error
	 */
	public static function createDirectories(string $filePath): bool
	{
		$dirPath = self::dirname($filePath);

		if (file_exists($dirPath)) {
			return true;
		}

		return mkdir($dirPath, 0777, true);
	}

	/**
	 * Initializes a file by removing it if it exists and creating all parent directories.
	 * This ensures a clean slate for file creation:
	 * - Deletes the file if it already exists
	 * - Creates all necessary parent directories
	 * @param string $filePath The complete path to the file
	 */
	public static function initializeFile(string $filePath): void
	{
		$filePath = self::formatPath($filePath);

		// Delete file if it already exists
		if (file_exists($filePath)) {
			unlink($filePath);
		}

		// Create directories where the file will be located
		if (!file_exists(dirname($filePath))) {
			self::createDirectories($filePath);
		}
	}

}