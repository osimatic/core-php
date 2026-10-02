<?php

namespace Osimatic\FileSystem;

/**
 * Represents a file to be sent to the client (download or inline display).
 * This class encapsulates information about a file that will be outputted:
 * - The actual file path on the server
 * - The display name for the client (filename shown in browser/download dialog)
 *
 * Useful for scenarios where the stored filename differs from what should be shown to the user.
 */
class OutputFile
{
	/**
	 * The full file path of the file on the server.
	 * @var string|null
	 */
	private ?string $filePath;

	/**
	 * The displayed filename for the client (used in download/display).
	 * If null, the basename from filePath will be used.
	 * @var string|null
	 */
	private ?string $fileName;

	/**
	 * Creates a new OutputFile instance.
	 * @param string|null $filePath The server path to the file
	 * @param string|null $fileName The filename to display to the client
	 */
	public function __construct(?string $filePath=null, ?string $fileName=null)
	{
		$this->filePath = $filePath;
		$this->fileName = $fileName;
	}

	/**
	 * Gets the server file path.
	 * @return string|null The file path
	 */
	public function getFilePath(): ?string
	{
		return $this->filePath;
	}

	/**
	 * Sets the server file path.
	 * @param string|null $filePath The file path
	 * @return self Returns this instance for method chaining
	 */
	public function setFilePath(?string $filePath): self
	{
		$this->filePath = $filePath;
		return $this;
	}

	/**
	 * Gets the display filename for the client.
	 * @return string|null The display filename
	 */
	public function getFileName(): ?string
	{
		return $this->fileName;
	}

	/**
	 * Sets the display filename for the client.
	 * @param string|null $fileName The display filename
	 * @return self Returns this instance for method chaining
	 */
	public function setFileName(?string $fileName): self
	{
		$this->fileName = $fileName;
		return $this;
	}

	/**
	 * Gets the file extension based on the file path or display name.
	 * Handles common double extensions like .tar.gz using File::getExtension().
	 * @return string|null The file extension in lowercase (without dot), or null if not found
	 */
	public function getExtension(): ?string
	{
		$path = $this->filePath ?? $this->fileName;
		if (!$path) {
			return null;
		}

		$extension = File::getExtension($path);
		return !empty($extension) ? $extension : null;
	}
}