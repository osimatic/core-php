<?php

namespace Tests\FileSystem;

use Osimatic\FileSystem\File;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class FileTest extends TestCase
{
	/* ===================== Base64 Data Handling ===================== */

	public function testGetDataFromBase64Data(): void
	{
		// Valid base64 with prefix
		$data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
		$result = File::getDataFromBase64Data($data);
		$this->assertNotNull($result);
		$this->assertIsString($result);

		// Valid base64 without prefix
		$base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
		$result = File::getDataFromBase64Data($base64);
		$this->assertNotNull($result);

		// Invalid base64
		$this->assertNull(File::getDataFromBase64Data('invalid!!!'));

		// Cas où la partie après "base64," est vide
		$this->assertNull(File::getDataFromBase64Data('data:image/png;base64,'));

		// Base64 qui échoue au décodage strict
		$this->assertNull(File::getDataFromBase64Data('!!!invalid!!!'));
		$this->assertNull(File::getDataFromBase64Data('not@valid#base64'));

		// Base64 valide mais qui ne peut pas être réencodé identiquement (padding manquant)
		// Note: base64_decode en mode strict accepte 'YQ' et retourne 'a' — le re-encodage
		// n'est pas vérifié car cela pose pb avec certaines données légitimes sans padding.
		// $invalidPadding = 'YQ'; // 'a' sans padding correct
		// $this->assertNull(File::getDataFromBase64Data($invalidPadding));
	}

	public function testGetMimeTypeFromBase64Data(): void
	{
		// With mime type prefix
		$data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
		$this->assertEquals('image/png', File::getMimeTypeFromBase64Data($data));

		// PDF signature
		$pdfData = base64_encode("\x25\x50\x44\x46\x2D");
		$this->assertEquals('application/pdf', File::getMimeTypeFromBase64Data($pdfData));

		// JPEG signature
		$jpegData = base64_encode("\xFF\xD8\xFF");
		$this->assertEquals('image/jpeg', File::getMimeTypeFromBase64Data($jpegData));

		// PNG signature
		$pngData = base64_encode("\x89\x50\x4E\x47\x0D\x0A\x1A\x0A");
		$this->assertEquals('image/png', File::getMimeTypeFromBase64Data($pngData));

		// GIF signature
		$gifData = base64_encode("\x47\x49\x46\x38\x39\x61");
		$this->assertEquals('image/gif', File::getMimeTypeFromBase64Data($gifData));

		// Bitmap signature
		$bmpData = base64_encode("\x42\x4D");
		$this->assertEquals('image/bmp', File::getMimeTypeFromBase64Data($bmpData));

		// ZIP signature
		$zipData = base64_encode("\x50\x4B\x03\x04");
		$this->assertEquals('application/zip', File::getMimeTypeFromBase64Data($zipData));

		// Cas où explode()[0] est vide
		$this->assertNull(File::getMimeTypeFromBase64Data('base64,iVBORw0KGgo='));

		// Cas où la seconde partie après explode est vide
		// Note: This actually returns the mime type from the prefix 'data:image/png;'
		$this->assertEquals('image/png', File::getMimeTypeFromBase64Data('data:image/png;base64,'));

		// Test des signatures de fichiers non couvertes

		// 3GPP video
		$data3gpp = base64_encode("\x66\x74\x79\x70\x33\x67");
		$this->assertEquals('video/3gpp', File::getMimeTypeFromBase64Data($data3gpp));

		// EXE (Windows executable)
		$dataExe = base64_encode("\x4D\x5A");
		$this->assertEquals('application/octet-stream', File::getMimeTypeFromBase64Data($dataExe));

		// MP4 video
		$dataMp4 = base64_encode("\x66\x74\x79\x70\x69\x73\x6F\x6D");
		$this->assertEquals('video/mp4', File::getMimeTypeFromBase64Data($dataMp4));

		// TIFF image
		$dataTiff1 = base64_encode("\x49\x49\x2A\x00");
		$this->assertEquals('image/tiff', File::getMimeTypeFromBase64Data($dataTiff1));
		$dataTiff2 = base64_encode("\x4D\x4D\x00\x2A");
		$this->assertEquals('image/tiff', File::getMimeTypeFromBase64Data($dataTiff2));

		// WEBP image
		$dataWebp = base64_encode("\x52\x49\x46\x46");
		$this->assertEquals('image/webp', File::getMimeTypeFromBase64Data($dataWebp));

		// RTF document
		$dataRtf = base64_encode("\x7B\x5C\x72\x74\x66\x31");
		$this->assertEquals('application/rtf', File::getMimeTypeFromBase64Data($dataRtf));

		// QuickTime video
		$dataQt = base64_encode("\x71\x74\x20\x20");
		$this->assertEquals('video/quicktime', File::getMimeTypeFromBase64Data($dataQt));
	}

	/* ===================== File Extension ===================== */

	public function testGetExtension(): void
	{
		// Simple extensions (default behavior)
		$this->assertEquals('txt', File::getExtension('file.txt'));
		$this->assertEquals('pdf', File::getExtension('/path/to/document.pdf'));
		$this->assertEquals('jpg', File::getExtension('C:\\Users\\test\\image.jpg'));
		$this->assertEquals('', File::getExtension('noextension'));

		// Double extensions without includeDoubleExtension parameter (returns last extension only)
		$this->assertEquals('gz', File::getExtension('archive.tar.gz'));
		$this->assertEquals('bz2', File::getExtension('backup.tar.bz2'));
		$this->assertEquals('xz', File::getExtension('file.tar.xz'));

		// Double extensions with includeDoubleExtension = true
		$this->assertEquals('tar.gz', File::getExtension('archive.tar.gz', true));
		$this->assertEquals('tar.bz2', File::getExtension('backup.tar.bz2', true));
		$this->assertEquals('tar.xz', File::getExtension('file.tar.xz', true));
		$this->assertEquals('tar.z', File::getExtension('old.tar.z', true));
		$this->assertEquals('tar.lz', File::getExtension('compressed.tar.lz', true));

		// Non-double extensions should return simple extension even with includeDoubleExtension = true
		$this->assertEquals('txt', File::getExtension('file.txt', true));
		$this->assertEquals('pdf', File::getExtension('document.pdf', true));
	}

	public function testReplaceExtension(): void
	{
		$this->assertEquals('file.pdf', File::replaceExtension('file.txt', 'pdf'));
		$this->assertEquals('file.pdf', File::replaceExtension('file.txt', '.pdf'));
		$this->assertEquals('/path/to/document.jpg', File::replaceExtension('/path/to/document.png', 'jpg'));
		$this->assertEquals('C:\test\file.docx', File::replaceExtension('C:\test\file.txt', 'docx'));
		$this->assertEquals('noext.txt', File::replaceExtension('noext', 'txt'));
	}

	public function testAddSuffixToFilename(): void
	{
		// Simple filename with extension
		$this->assertEquals('file_backup.txt', File::addSuffixToFilename('file.txt', '_backup'));
		$this->assertEquals('document_v2.pdf', File::addSuffixToFilename('document.pdf', '_v2'));

		// Filename with full path (Unix-style)
		$this->assertEquals('/path/to/file_backup.txt', File::addSuffixToFilename('/path/to/file.txt', '_backup'));
		$this->assertEquals('/var/www/document_converted.pdf', File::addSuffixToFilename('/var/www/document.pdf', '_converted'));

		// Filename with full path (Windows-style)
		$this->assertEquals('C:\Users\test\file_copy.docx', File::addSuffixToFilename('C:\Users\test\file.docx', '_copy'));

		// Filename without extension
		$this->assertEquals('readme_old', File::addSuffixToFilename('readme', '_old'));

		// Multiple dots in filename
		$this->assertEquals('archive.tar_backup.gz', File::addSuffixToFilename('archive.tar.gz', '_backup'));

		// Empty suffix
		$this->assertEquals('file.txt', File::addSuffixToFilename('file.txt', ''));

		// Suffix with special characters
		$this->assertEquals('file-2024-01-26.txt', File::addSuffixToFilename('file.txt', '-2024-01-26'));
	}

	public function testAddPrefixToFilename(): void
	{
		// Simple filename with extension
		$this->assertEquals('backup_file.txt', File::addPrefixToFilename('file.txt', 'backup_'));
		$this->assertEquals('draft_document.pdf', File::addPrefixToFilename('document.pdf', 'draft_'));

		// Filename with full path (Unix-style)
		$this->assertEquals('/path/to/backup_file.txt', File::addPrefixToFilename('/path/to/file.txt', 'backup_'));
		$this->assertEquals('/var/www/temp_document.pdf', File::addPrefixToFilename('/var/www/document.pdf', 'temp_'));

		// Filename with full path (Windows-style)
		$this->assertEquals('C:\Users\test\old_file.docx', File::addPrefixToFilename('C:\Users\test\file.docx', 'old_'));

		// Filename without extension
		$this->assertEquals('new_readme', File::addPrefixToFilename('readme', 'new_'));

		// Multiple dots in filename
		$this->assertEquals('copy_archive.tar.gz', File::addPrefixToFilename('archive.tar.gz', 'copy_'));

		// Empty prefix
		$this->assertEquals('file.txt', File::addPrefixToFilename('file.txt', ''));

		// Prefix with special characters
		$this->assertEquals('2024-01-26_file.txt', File::addPrefixToFilename('file.txt', '2024-01-26_'));

		// Prefix with numbers
		$this->assertEquals('123_document.pdf', File::addPrefixToFilename('document.pdf', '123_'));
	}

	/* ===================== File Size Formatting ===================== */

	public function testFormatSize(): void
	{
		// Bytes
		$this->assertEquals('0.00 o', File::formatSize(0));
		$this->assertEquals('100.00 o', File::formatSize(100));
		$this->assertEquals('1023.00 o', File::formatSize(1023));

		// Kilobytes
		$this->assertEquals('1.00 Ko', File::formatSize(1024));
		$this->assertEquals('10.00 Ko', File::formatSize(10240));

		// Megabytes
		$this->assertEquals('1.00 Mo', File::formatSize(1048576)); // 1024^2
		$this->assertEquals('5.50 Mo', File::formatSize(5767168)); // 5.5 MB

		// Gigabytes
		$this->assertEquals('1.00 Go', File::formatSize(1073741824)); // 1024^3
		$this->assertEquals('2.50 Go', File::formatSize(2684354560)); // 2.5 GB

		// Custom decimal places
		$this->assertEquals('1.5 Mo', File::formatSize(1572864, 1)); // 1 decimal
		$this->assertEquals('1 Mo', File::formatSize(1048576, 0)); // No decimals

		// Negative values (edge case)
		$this->assertEquals('0.00 o', File::formatSize(-100));

		// 5 Petabytes (au-delà de Terabytes)
		$petabytes = 5 * pow(1024, 5);
		$result = File::formatSize($petabytes);

		// Should return in TB since it's the highest unit
		$this->assertStringContainsString('To', $result);
		$this->assertStringContainsString('5120.00', $result); // 5 * 1024 TB

		// With Exactly Zero
		$this->assertEquals('0.00 o', File::formatSize(0));
	}

	/* ===================== MIME Type / Extension Mapping ===================== */

	public function testGetMimeTypeFromExtension(): void
	{
		// Image formats
		$this->assertEquals('image/jpeg', File::getMimeTypeFromExtension('jpg'));
		$this->assertEquals('image/jpeg', File::getMimeTypeFromExtension('.jpg'));
		$this->assertEquals('image/png', File::getMimeTypeFromExtension('png'));
		$this->assertEquals('image/gif', File::getMimeTypeFromExtension('gif'));

		// Document formats
		$this->assertEquals('application/pdf', File::getMimeTypeFromExtension('pdf'));
		$this->assertEquals('application/msword', File::getMimeTypeFromExtension('doc'));
		$this->assertEquals('application/vnd.openxmlformats-officedocument.wordprocessingml.document', File::getMimeTypeFromExtension('docx'));
		$this->assertEquals('application/vnd.ms-excel', File::getMimeTypeFromExtension('xls'));
		$this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', File::getMimeTypeFromExtension('xlsx'));

		// Text formats
		$this->assertEquals('text/plain', File::getMimeTypeFromExtension('txt'));
		$this->assertEquals('text/html', File::getMimeTypeFromExtension('html'));
		$this->assertEquals('text/css', File::getMimeTypeFromExtension('css'));
		$this->assertEquals('text/csv', File::getMimeTypeFromExtension('csv'));

		// Archive formats
		$this->assertEquals('application/zip', File::getMimeTypeFromExtension('zip'));

		// Case insensitivity
		$this->assertEquals('image/jpeg', File::getMimeTypeFromExtension('JPG'));
		$this->assertEquals('image/jpeg', File::getMimeTypeFromExtension('JpG'));

		// Unknown extension
		$this->assertNull(File::getMimeTypeFromExtension('unknown'));
	}

	public function testGetExtensionFromMimeType(): void
	{
		// Image formats
		$this->assertEquals('jpg', File::getExtensionFromMimeType('image/jpeg'));
		$this->assertEquals('png', File::getExtensionFromMimeType('image/png'));
		$this->assertEquals('gif', File::getExtensionFromMimeType('image/gif'));

		// Document formats
		$this->assertEquals('pdf', File::getExtensionFromMimeType('application/pdf'));
		$this->assertEquals('doc', File::getExtensionFromMimeType('application/msword'));
		$this->assertEquals('xlsx', File::getExtensionFromMimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));

		// Text formats
		$this->assertEquals('txt', File::getExtensionFromMimeType('text/plain'));
		$this->assertEquals('html', File::getExtensionFromMimeType('text/html'));
		$this->assertEquals('css', File::getExtensionFromMimeType('text/css'));

		// Archive formats
		$this->assertEquals('zip', File::getExtensionFromMimeType('application/zip'));

		// Case insensitivity
		$this->assertEquals('jpg', File::getExtensionFromMimeType('IMAGE/JPEG'));

		// Unknown MIME type
		$this->assertNull(File::getExtensionFromMimeType('application/x-unknown'));
	}

	public function testGetMimeTypeForFile(): void
	{
		// With extension
		$this->assertEquals('image/jpeg', File::getMimeTypeForFile('image.jpg'));
		$this->assertEquals('application/pdf', File::getMimeTypeForFile('document.pdf'));
		$this->assertEquals('text/plain', File::getMimeTypeForFile('readme.txt'));

		// With full path
		$this->assertEquals('image/png', File::getMimeTypeForFile('/var/www/images/photo.png'));
		$this->assertEquals('application/zip', File::getMimeTypeForFile('C:\Downloads\archive.zip'));

		// With query string (like Bitly URLs)
		$this->assertEquals('image/jpeg', File::getMimeTypeForFile('photo.jpg?v=123&size=large'));

		// Unknown extension defaults to octet-stream
		$this->assertEquals('application/octet-stream', File::getMimeTypeForFile('file.unknown'));
		$this->assertEquals('application/octet-stream', File::getMimeTypeForFile('noextension'));
	}

	/* ===================== Multibyte-safe pathinfo ===================== */

	public function testMbPathinfo(): void
	{
		// Basic path
		$result = File::mb_pathinfo('/path/to/file.txt');
		$this->assertEquals('/path/to', $result['dirname']);
		$this->assertEquals('file.txt', $result['basename']);
		$this->assertEquals('txt', $result['extension']);
		$this->assertEquals('file', $result['filename']);

		// With specific option
		$this->assertEquals('txt', File::mb_pathinfo('/path/to/file.txt', PATHINFO_EXTENSION));
		$this->assertEquals('file', File::mb_pathinfo('/path/to/file.txt', PATHINFO_FILENAME));
		$this->assertEquals('file.txt', File::mb_pathinfo('/path/to/file.txt', PATHINFO_BASENAME));
		$this->assertEquals('/path/to', File::mb_pathinfo('/path/to/file.txt', PATHINFO_DIRNAME));

		// Windows path
		$result = File::mb_pathinfo('C:\Users\test\document.pdf');
		$this->assertEquals('C:\Users\test', $result['dirname']);
		$this->assertEquals('document.pdf', $result['basename']);
		$this->assertEquals('pdf', $result['extension']);
		$this->assertEquals('document', $result['filename']);

		// No extension
		$result = File::mb_pathinfo('/path/to/file');
		$this->assertEquals('', $result['extension']);
		$this->assertEquals('file', $result['filename']);

		// Multiple extensions
		$result = File::mb_pathinfo('archive.tar.gz');
		$this->assertEquals('gz', $result['extension']);
		$this->assertEquals('archive.tar', $result['filename']);

		// Filename only
		$result = File::mb_pathinfo('file.txt');
		$this->assertEquals('', $result['dirname']);
		$this->assertEquals('file.txt', $result['basename']);

		// With multibyte characters (UTF-8)
		$result = File::mb_pathinfo('/chemin/vers/fichier-été.txt');
		$this->assertEquals('fichier-été.txt', $result['basename']);
		$this->assertEquals('fichier-été', $result['filename']);
	}

	/* ===================== File Extension of UploadedFile ===================== */

	public function testGetExtensionOfUploadedFileWithInputFile(): void
	{
		$inputFile = new \Osimatic\FileSystem\InputFile();
		$inputFile->setOriginalFileName('document.pdf');

		$extension = File::getExtensionOfUploadedFile($inputFile);
		$this->assertEquals('pdf', $extension);
	}

	public function testGetExtensionOfUploadedFileWithSymfonyUploadedFile(): void
	{
		$uploadedFile = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
		$uploadedFile->method('getClientOriginalExtension')->willReturn('jpg');

		$extension = File::getExtensionOfUploadedFile($uploadedFile);
		$this->assertEquals('jpg', $extension);
	}

	/* ===================== File Upload Processing ===================== */

	public function testGetUploadedFileFromRequestWithBase64Data(): void
	{
		$base64Data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

		$request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
		$request->request = new \Symfony\Component\HttpFoundation\InputBag(['file_data' => $base64Data]);

		$logger = $this->createMock(\Psr\Log\LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with('Uploaded file from base64 content.');

		$result = File::getUploadedFileFromRequest($request, 'file', 'file_data', [], $logger);

		$this->assertInstanceOf(\Osimatic\FileSystem\InputFile::class, $result);
		$this->assertNotNull($result->getData());
		$this->assertEquals('image/png', $result->getMimeType());
	}

	public function testGetUploadedFileFromRequestWithInvalidBase64(): void
	{
		$invalidBase64 = 'invalid!!!base64';

		$request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
		$request->request = new \Symfony\Component\HttpFoundation\InputBag(['file_data' => $invalidBase64]);

		$logger = $this->createMock(\Psr\Log\LoggerInterface::class);
		$logger->expects($this->atLeastOnce())
			->method('info');

		$result = File::getUploadedFileFromRequest($request, 'file', 'file_data', [], $logger);

		$this->assertNull($result);
	}

	public function testGetUploadedFileFromRequestWithFormUpload(): void
	{
		$uploadedFile = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
		$uploadedFile->method('getSize')->willReturn(1024);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);

		$files = $this->createMock(\Symfony\Component\HttpFoundation\FileBag::class);
		$files->method('get')->willReturn($uploadedFile);

		$request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
		$request->request = new \Symfony\Component\HttpFoundation\InputBag([]);
		$request->files = $files;

		$logger = $this->createMock(\Psr\Log\LoggerInterface::class);
		$logger->expects($this->atLeastOnce())
			->method('info');

		$result = File::getUploadedFileFromRequest($request, 'file', 'file_data', [], $logger);

		$this->assertInstanceOf(\Symfony\Component\HttpFoundation\File\UploadedFile::class, $result);
	}

	public function testGetUploadedFileFromRequestWithUploadError(): void
	{
		$uploadedFile = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
		$uploadedFile->method('getSize')->willReturn(1024);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_INI_SIZE);
		$uploadedFile->method('getErrorMessage')->willReturn('The file exceeds the upload_max_filesize directive');

		$files = $this->createMock(\Symfony\Component\HttpFoundation\FileBag::class);
		$files->method('get')->willReturn($uploadedFile);

		$request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
		$request->request = new \Symfony\Component\HttpFoundation\InputBag([]);
		$request->files = $files;

		$logger = $this->createMock(\Psr\Log\LoggerInterface::class);
		$logger->expects($this->atLeastOnce())
			->method('info');

		$result = File::getUploadedFileFromRequest($request, 'file', 'file_data', [], $logger);

		$this->assertNull($result);
	}

	public function testGetUploadedFileFromRequestWithInvalidFormat(): void
	{
		$uploadedFile = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
		$uploadedFile->method('getSize')->willReturn(1024);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);
		$uploadedFile->method('getRealPath')->willReturn(__FILE__); // Use this test file
		$uploadedFile->method('getClientOriginalName')->willReturn('test.php');

		$files = $this->createMock(\Symfony\Component\HttpFoundation\FileBag::class);
		$files->method('get')->willReturn($uploadedFile);

		$request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
		$request->request = new \Symfony\Component\HttpFoundation\InputBag([]);
		$request->files = $files;

		$logger = $this->createMock(\Psr\Log\LoggerInterface::class);
		$logger->expects($this->atLeastOnce())
			->method('info');

		// Only allow PDF files
		$result = File::getUploadedFileFromRequest($request, 'file', 'file_data', ['pdf'], $logger);

		$this->assertNull($result);
	}

	public function testGetUploadedFileFromRequestNotFound(): void
	{
		$request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
		$request->request = new \Symfony\Component\HttpFoundation\InputBag([]);

		$files = $this->createMock(\Symfony\Component\HttpFoundation\FileBag::class);
		$files->method('get')->willReturn(null);
		$request->files = $files;

		$logger = $this->createMock(\Psr\Log\LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with('Uploaded file not found in request.');

		$result = File::getUploadedFileFromRequest($request, 'file', 'file_data', [], $logger);

		$this->assertNull($result);
	}

	public function testMoveUploadedFileWithInputFileData(): void
	{
		// Create temporary file to write to
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');

		$inputFile = new \Osimatic\FileSystem\InputFile();
		$inputFile->setData('test content for file');

		$result = File::moveUploadedFile($inputFile, $tempFile);

		$this->assertTrue($result);
		$this->assertFileExists($tempFile);
		$this->assertEquals('test content for file', file_get_contents($tempFile));

		// Cleanup
		unlink($tempFile);
	}

	// Note: testMoveUploadedFileWithInputFileDataFailure removed because it's not portable
	// (FileSystem::createDirectories() may succeed on some systems)

	public function testMoveUploadedFileReplacesExistingFile(): void
	{
		// Create existing file
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'old content');

		$inputFile = new \Osimatic\FileSystem\InputFile();
		$inputFile->setData('new content');

		$result = File::moveUploadedFile($inputFile, $tempFile);

		$this->assertTrue($result);
		$this->assertEquals('new content', file_get_contents($tempFile));

		// Cleanup
		unlink($tempFile);
	}

	public function testMoveUploadedFileWithEmptyData(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');

		$inputFile = new \Osimatic\FileSystem\InputFile();
		// No data set, no uploaded file path

		$result = File::moveUploadedFile($inputFile, $tempFile);

		$this->assertFalse($result);

		// Cleanup
		if (file_exists($tempFile)) {
			unlink($tempFile);
		}
	}

	/* ===================== Upload Limits ===================== */

	public function testConvertIniSizeToBytes(): void
	{
		$this->assertSame(0, File::convertIniSizeToBytes(''));
		$this->assertSame(100, File::convertIniSizeToBytes('100'));
		$this->assertSame(1024, File::convertIniSizeToBytes('1K'));
		$this->assertSame(2 * 1024 * 1024, File::convertIniSizeToBytes('2M'));
		$this->assertSame(3 * 1024 * 1024 * 1024, File::convertIniSizeToBytes('3G'));
		// Unité insensible à la casse
		$this->assertSame(4 * 1024 * 1024, File::convertIniSizeToBytes('4m'));
		$this->assertSame(0, File::convertIniSizeToBytes('0'));
	}

	public function testGetPostMaxSize(): void
	{
		$this->assertSame(File::convertIniSizeToBytes((string) ini_get('post_max_size')), File::getPostMaxSize());
	}

	public function testGetUploadMaxFileSize(): void
	{
		$this->assertSame(File::convertIniSizeToBytes((string) ini_get('upload_max_filesize')), File::getUploadMaxFileSize());
	}

	public function testIsPostMaxSizeExceeded(): void
	{
		$postMaxSize = File::getPostMaxSize();
		if ($postMaxSize <= 0) {
			$this->markTestSkipped('post_max_size illimité sur cet environnement.');
		}

		// Content-Length supérieur à post_max_size
		$requestTooLarge = \Symfony\Component\HttpFoundation\Request::create('/document', 'POST', [], [], [], ['CONTENT_LENGTH' => (string) ($postMaxSize + 1)]);
		$this->assertTrue(File::isPostMaxSizeExceeded($requestTooLarge));

		// Content-Length inférieur à post_max_size
		$requestOk = \Symfony\Component\HttpFoundation\Request::create('/document', 'POST', [], [], [], ['CONTENT_LENGTH' => '100']);
		$this->assertFalse(File::isPostMaxSizeExceeded($requestOk));
	}

	/* ===================== File Validation ===================== */

	public function testCheckWithValidFile(): void
	{
		// Create a temporary test file
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'test content');

		$result = File::check($tempFile, 'test.txt', ['.txt'], ['text/plain']);

		$this->assertTrue($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testCheckWithNonExistentFile(): void
	{
		$this->assertFalse(File::check('/non/existent/file.txt', 'file.txt'));
	}

	public function testCheckWithEmptyPath(): void
	{
		$this->assertFalse(File::check('', 'file.txt'));
	}

	public function testCheckWithInvalidExtension(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'test content');

		// File has no extension in name, but we require .pdf
		$result = File::check($tempFile, 'noextension', ['.pdf']);

		$this->assertFalse($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testCheckWithNotAllowedExtension(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'test content');

		// Extension is .txt but only .pdf is allowed
		$result = File::check($tempFile, 'file.txt', ['.pdf', '.doc']);

		$this->assertFalse($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testCheckWithInvalidMimeType(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'test content');

		// Real MIME type will be text/plain, but we require application/pdf
		$result = File::check($tempFile, 'file.txt', ['.txt'], ['application/pdf']);

		$this->assertFalse($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testCheckWithNoRestrictions(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'test content');

		// No restrictions, should pass
		$result = File::check($tempFile, 'file.txt', null, null);

		$this->assertTrue($result);

		// Cleanup
		unlink($tempFile);
	}

	/* ===================== Security & Validation ===================== */

	public function testValidateFilename(): void
	{
		// Valid filenames - should not throw
		File::validateFilename('test.json');
		File::validateFilename('file.txt');
		File::validateFilename('document-2024.pdf');
		File::validateFilename('my_file_123.csv');
		$this->assertTrue(true); // Assert that no exception was thrown

		// Empty filename
		try {
			File::validateFilename('');
			$this->fail('Expected InvalidArgumentException for empty filename');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Filename cannot be empty', $e->getMessage());
		}

		// Parent directory reference (path traversal)
		try {
			File::validateFilename('../etc/passwd');
			$this->fail('Expected InvalidArgumentException for parent directory reference');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Filename cannot contain parent directory references', $e->getMessage());
		}

		// Forward slash
		try {
			File::validateFilename('subdir/file.txt');
			$this->fail('Expected InvalidArgumentException for forward slash');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Filename cannot contain directory separators', $e->getMessage());
		}

		// Backslash
		try {
			File::validateFilename('subdir\\file.txt');
			$this->fail('Expected InvalidArgumentException for backslash');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Filename cannot contain directory separators', $e->getMessage());
		}

		// DIRECTORY_SEPARATOR
		try {
			File::validateFilename('subdir' . DIRECTORY_SEPARATOR . 'file.txt');
			$this->fail('Expected InvalidArgumentException for DIRECTORY_SEPARATOR');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Filename cannot contain directory separators', $e->getMessage());
		}

		// Null byte
		try {
			File::validateFilename("file\0.txt");
			$this->fail('Expected InvalidArgumentException for null byte');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Filename cannot contain null bytes', $e->getMessage());
		}

		// Absolute path
		try {
			File::validateFilename('/etc/passwd');
			$this->fail('Expected InvalidArgumentException for absolute path');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Filename cannot contain directory separators', $e->getMessage());
		}
	}

	public function testBuildSecurePath(): void
	{
		$tempDir = sys_get_temp_dir();
		$filename = 'test.json';

		// Valid case
		$result = File::buildSecurePath($tempDir, $filename);
		$this->assertStringStartsWith($tempDir, $result);
		$this->assertStringEndsWith($filename, $result);
		$this->assertStringContainsString(DIRECTORY_SEPARATOR, $result);

		// Trailing slashes should be handled
		$result1 = File::buildSecurePath($tempDir . '/', $filename);
		$result2 = File::buildSecurePath($tempDir, $filename);
		$this->assertEquals($result1, $result2);

		// Non-existent directory allowed when requireExistingDirectory = false
		$nonExistentDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nonexistent_' . uniqid();
		$result = File::buildSecurePath($nonExistentDir, $filename, false);
		$this->assertStringStartsWith($nonExistentDir, $result);
		$this->assertStringEndsWith($filename, $result);

		// Empty base directory
		try {
			File::buildSecurePath('', 'file.txt');
			$this->fail('Expected InvalidArgumentException for empty base directory');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Base directory cannot be empty', $e->getMessage());
		}

		// Non-existent directory with requireExistingDirectory = true (default)
		try {
			$nonExistentDir2 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nonexistent_' . uniqid();
			File::buildSecurePath($nonExistentDir2, 'file.txt', true);
			$this->fail('Expected InvalidArgumentException for non-existent directory');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Base directory does not exist', $e->getMessage());
		}

		// Invalid filename (path traversal)
		try {
			File::buildSecurePath($tempDir, '../etc/passwd');
			$this->fail('Expected InvalidArgumentException for path traversal');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Filename cannot contain parent directory references', $e->getMessage());
		}

		// Path traversal with multiple levels
		try {
			File::buildSecurePath($tempDir, '..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'etc'.DIRECTORY_SEPARATOR.'passwd');
			$this->fail('Expected InvalidArgumentException for multi-level path traversal');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('parent directory references', $e->getMessage());
		}
	}

	public function testEnsureExtension(): void
	{
		// Filename without extension
		$this->assertEquals('file.json', File::ensureExtension('file', 'json'));

		// Filename already has extension
		$this->assertEquals('file.json', File::ensureExtension('file.json', 'json'));

		// Extension with leading dot
		$this->assertEquals('file.txt', File::ensureExtension('file', '.txt'));

		// Case insensitive check
		$this->assertEquals('file.JSON', File::ensureExtension('file.JSON', 'json'));
		$this->assertEquals('file.TXT', File::ensureExtension('file.TXT', 'txt'));

		// Filename has different extension - should append the new one
		$this->assertEquals('file.txt.json', File::ensureExtension('file.txt', 'json'));

		// Multiple dots in filename
		$this->assertEquals('my.file.name.json', File::ensureExtension('my.file.name', 'json'));

		// Empty filename
		$this->assertEquals('.json', File::ensureExtension('', 'json'));

		// Path preservation (though buildSecurePath should be used for paths)
		$this->assertEquals('path/to/file.json', File::ensureExtension('path/to/file', 'json'));
	}

	/* ===================== File Output ===================== */

	public function testGetHttpResponseWithExistingFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'test file content');

		$response = File::getHttpResponse($tempFile, 'download.txt', true);

		$this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('test file content', $response->getContent());
		$this->assertEquals('application/force-download', $response->headers->get('Content-Type'));
		$this->assertStringContainsString('download.txt', $response->headers->get('Content-Disposition'));

		// Cleanup
		unlink($tempFile);
	}

	public function testGetHttpResponseWithNonExistentFile(): void
	{
		$response = File::getHttpResponse('/non/existent/file.txt', 'test.txt');

		$this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
		$this->assertEquals(400, $response->getStatusCode());
		$this->assertEquals('file_not_found', $response->getContent());
	}

	public function testGetHttpResponseForInlineDisplay(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'test content');

		$response = File::getHttpResponse($tempFile, 'image.jpg', false, 'image/jpeg');

		$this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
		$this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
		$this->assertStringNotContainsString('force-download', $response->headers->get('Content-Type'));

		// Cleanup
		unlink($tempFile);
	}

	public function testGetHttpResponseWithCustomTransferEncoding(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'test content');

		$response = File::getHttpResponse($tempFile, 'file.bin', true, null, 'chunked');

		$this->assertEquals('chunked', $response->headers->get('Content-Transfer-Encoding'));

		// Cleanup
		unlink($tempFile);
	}

	public function testOutputFileWithValidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'output content');

		$outputFile = new \Osimatic\FileSystem\OutputFile($tempFile, 'display.txt');

		// getHttpResponse mode (sendResponse = false)
		$response = File::getHttpResponse($outputFile->getFilePath(), $outputFile->getFileName(), false, 'text/plain');

		$this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
		$this->assertEquals('text/plain', $response->headers->get('Content-Type'));

		// Cleanup
		unlink($tempFile);
	}

	public function testOutputFileWithNullFilePath(): void
	{
		$outputFile = new \Osimatic\FileSystem\OutputFile(null, 'test.txt');

		// outputFile method returns early if filePath is null
		// We can't directly test outputFile() since it calls exit(), but we can test via getHttpResponse
		$this->assertNull($outputFile->getFilePath());
	}

	public function testDownloadFileWithValidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tempFile, 'download content');

		$outputFile = new \Osimatic\FileSystem\OutputFile($tempFile, 'download.bin');

		$response = File::getHttpResponse($outputFile->getFilePath(), $outputFile->getFileName(), true);

		$this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
		$this->assertEquals('application/force-download', $response->headers->get('Content-Type'));
		$this->assertStringContainsString('download.bin', $response->headers->get('Content-Disposition'));

		// Cleanup
		unlink($tempFile);
	}

	/* ===================== Extensions and MIME Types ===================== */

	public function testGetExtensionsAndMimeTypes(): void
	{
		$mappings = File::getExtensionsAndMimeTypes();

		$this->assertIsArray($mappings);
		$this->assertNotEmpty($mappings);

		// Verify structure: each entry should be [extensions_array, mime_types_array]
		foreach ($mappings as $mapping) {
			$this->assertIsArray($mapping);
			$this->assertCount(2, $mapping);
			$this->assertIsArray($mapping[0]); // extensions
			$this->assertIsArray($mapping[1]); // mime types
		}

		// Check that common formats are included
		$allExtensions = array_merge(...array_column($mappings, 0));
		$this->assertContains('pdf', $allExtensions);
		$this->assertContains('jpg', $allExtensions);
		$this->assertContains('png', $allExtensions);
		$this->assertContains('zip', $allExtensions);
		$this->assertContains('mp4', $allExtensions);
	}

	public function testGetMimeTypeFromExtensionWithCustomMapping(): void
	{
		$customMapping = [
			[['custom'], ['application/x-custom']],
		];

		$result = File::getMimeTypeFromExtension('custom', $customMapping);
		$this->assertEquals('application/x-custom', $result);
	}

	public function testGetExtensionFromMimeTypeWithCustomMapping(): void
	{
		$customMapping = [
			[['myext'], ['application/x-mytype']],
		];

		$result = File::getExtensionFromMimeType('application/x-mytype', $customMapping);
		$this->assertEquals('myext', $result);
	}
}