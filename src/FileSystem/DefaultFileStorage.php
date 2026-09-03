<?php

namespace Osimatic\FileSystem;

/**
 * Static holder for the default file storage instance, configured once per request.
 * This allows code with no access to dependency injection (e.g. Doctrine entities) to resolve public file URLs.
 */
class DefaultFileStorage
{
	// ========== Properties ==========

	private static ?FileStorageInterface $instance = null;

	// ========== Public Methods ==========

	/**
	 * Sets the default file storage instance.
	 * @param FileStorageInterface $storage The file storage instance to use as default
	 * @return void
	 */
	public static function set(FileStorageInterface $storage): void
	{
		self::$instance = $storage;
	}

	/**
	 * Returns the default file storage instance, if it has been set.
	 * @return FileStorageInterface|null The default file storage instance, or null if none has been set
	 */
	public static function get(): ?FileStorageInterface
	{
		return self::$instance;
	}

}