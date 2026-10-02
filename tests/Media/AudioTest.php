<?php

declare(strict_types=1);

namespace Tests\Media;

use Osimatic\Media\Audio;
use PHPUnit\Framework\TestCase;

final class AudioTest extends TestCase
{
	/* ===================== Constants ===================== */

	public function testMp3Constants(): void
	{
		$this->assertSame('mp3', Audio::MP3_FORMAT);
		$this->assertSame('.mp3', Audio::MP3_EXTENSION);
		$this->assertIsArray(Audio::MP3_EXTENSIONS);
		$this->assertContains('.mp3', Audio::MP3_EXTENSIONS);
		$this->assertIsArray(Audio::MP3_MIME_TYPES);
		$this->assertContains('audio/mpeg', Audio::MP3_MIME_TYPES);
	}

	public function testWavConstants(): void
	{
		$this->assertSame('wav', Audio::WAV_FORMAT);
		$this->assertSame('.wav', Audio::WAV_EXTENSION);
		$this->assertIsArray(Audio::WAV_MIME_TYPES);
		$this->assertContains('audio/x-wav', Audio::WAV_MIME_TYPES);
	}

	public function testOggConstants(): void
	{
		$this->assertSame('ogg', Audio::OGG_FORMAT);
		$this->assertSame('.ogg', Audio::OGG_EXTENSION);
		$this->assertIsArray(Audio::OGG_EXTENSIONS);
		$this->assertContains('.ogg', Audio::OGG_EXTENSIONS);
		$this->assertIsArray(Audio::OGG_MIME_TYPES);
		$this->assertContains('audio/ogg', Audio::OGG_MIME_TYPES);
	}

	public function testAacConstants(): void
	{
		$this->assertSame('aac', Audio::AAC_FORMAT);
		$this->assertSame('.aac', Audio::AAC_EXTENSION);
		$this->assertIsArray(Audio::AAC_MIME_TYPES);
		$this->assertContains('audio/x-aac', Audio::AAC_MIME_TYPES);
	}

	public function testAiffConstants(): void
	{
		$this->assertSame('aiff', Audio::AIFF_FORMAT);
		$this->assertSame('.aiff', Audio::AIFF_EXTENSION);
		$this->assertIsArray(Audio::AIFF_EXTENSIONS);
		$this->assertContains('.aiff', Audio::AIFF_EXTENSIONS);
		$this->assertIsArray(Audio::AIFF_MIME_TYPES);
		$this->assertContains('audio/x-aiff', Audio::AIFF_MIME_TYPES);
	}

	public function testWmaConstants(): void
	{
		$this->assertSame('wma', Audio::WMA_FORMAT);
		$this->assertSame('.wma', Audio::WMA_EXTENSION);
		$this->assertIsArray(Audio::WMA_MIME_TYPES);
		$this->assertContains('audio/x-ms-wma', Audio::WMA_MIME_TYPES);
	}

	public function testWebmConstants(): void
	{
		$this->assertSame('webm', Audio::WEBM_FORMAT);
		$this->assertSame('.weba', Audio::WEBM_EXTENSION);
		$this->assertIsArray(Audio::WEBM_MIME_TYPES);
		$this->assertContains('audio/webm', Audio::WEBM_MIME_TYPES);
	}

	/* ===================== getExtensionsAndMimeTypes() ===================== */

	public function testGetExtensionsAndMimeTypes(): void
	{
		$result = Audio::getExtensionsAndMimeTypes();
		$this->assertIsArray($result);
		$this->assertArrayHasKey('mp3', $result);
		$this->assertArrayHasKey('wav', $result);
		$this->assertArrayHasKey('ogg', $result);
		$this->assertArrayHasKey('aac', $result);
		$this->assertArrayHasKey('aiff', $result);
		$this->assertArrayHasKey('wma', $result);
		$this->assertArrayHasKey('weba', $result);
	}

	public function testGetExtensionsAndMimeTypesMp3Structure(): void
	{
		$result = Audio::getExtensionsAndMimeTypes();
		$this->assertCount(2, $result['mp3']);
		$this->assertIsArray($result['mp3'][0]); // Extensions
		$this->assertIsArray($result['mp3'][1]); // Mime types
	}

	/* ===================== getMimeTypeFromExtension() ===================== */

	public function testGetMimeTypeFromExtensionWithMp3(): void
	{
		$this->assertSame('audio/mpeg', Audio::getMimeTypeFromExtension('.mp3'));
		$this->assertSame('audio/mpeg', Audio::getMimeTypeFromExtension('.mpga'));
	}

	public function testGetMimeTypeFromExtensionWithWav(): void
	{
		$this->assertSame('audio/x-wav', Audio::getMimeTypeFromExtension('.wav'));
	}

	public function testGetMimeTypeFromExtensionWithOgg(): void
	{
		$this->assertSame('audio/ogg', Audio::getMimeTypeFromExtension('.ogg'));
	}

	public function testGetMimeTypeFromExtensionWithAac(): void
	{
		$this->assertSame('audio/x-aac', Audio::getMimeTypeFromExtension('.aac'));
	}

	public function testGetMimeTypeFromExtensionWithAiff(): void
	{
		$this->assertSame('audio/x-aiff', Audio::getMimeTypeFromExtension('.aiff'));
		$this->assertSame('audio/x-aiff', Audio::getMimeTypeFromExtension('.aif'));
		$this->assertSame('audio/x-aiff', Audio::getMimeTypeFromExtension('.aifc'));
	}

	public function testGetMimeTypeFromExtensionWithWma(): void
	{
		$this->assertSame('audio/x-ms-wma', Audio::getMimeTypeFromExtension('.wma'));
	}

	public function testGetMimeTypeFromExtensionWithWebm(): void
	{
		$this->assertSame('audio/webm', Audio::getMimeTypeFromExtension('.weba'));
	}

	public function testGetMimeTypeFromExtensionWithInvalidExtension(): void
	{
		$this->assertNull(Audio::getMimeTypeFromExtension('.invalid'));
		$this->assertNull(Audio::getMimeTypeFromExtension('.txt'));
	}

	/* ===================== getExtensionFromMimeType() ===================== */

	public function testGetExtensionFromMimeTypeWithMp3(): void
	{
		$this->assertSame('mp3', Audio::getExtensionFromMimeType('audio/mpeg'));
	}

	public function testGetExtensionFromMimeTypeWithWav(): void
	{
		$this->assertSame('wav', Audio::getExtensionFromMimeType('audio/x-wav'));
	}

	public function testGetExtensionFromMimeTypeWithOgg(): void
	{
		$this->assertSame('ogg', Audio::getExtensionFromMimeType('audio/ogg'));
	}

	public function testGetExtensionFromMimeTypeWithAac(): void
	{
		$extension = Audio::getExtensionFromMimeType('audio/x-aac');
		$this->assertSame('aac', $extension);
	}

	public function testGetExtensionFromMimeTypeWithAlternativeAac(): void
	{
		$extension = Audio::getExtensionFromMimeType('audio/aac');
		$this->assertSame('aac', $extension);
	}

	public function testGetExtensionFromMimeTypeWithAiff(): void
	{
		$extension = Audio::getExtensionFromMimeType('audio/x-aiff');
		// Can be either 'aif' or 'aiff' depending on array order
		$this->assertContains($extension, ['aif', 'aiff']);
	}

	public function testGetExtensionFromMimeTypeWithWma(): void
	{
		$extension = Audio::getExtensionFromMimeType('audio/x-ms-wma');
		$this->assertSame('wma', $extension);
	}

	public function testGetExtensionFromMimeTypeWithWebm(): void
	{
		$extension = Audio::getExtensionFromMimeType('audio/webm');
		$this->assertSame('weba', $extension);
	}

	public function testGetExtensionFromMimeTypeWithInvalidMimeType(): void
	{
		$this->assertNull(Audio::getExtensionFromMimeType('invalid/mime'));
		$this->assertNull(Audio::getExtensionFromMimeType('text/plain'));
	}

	/* ===================== getInfos() ===================== */

	public function testGetInfosWithNonExistentFile(): void
	{
		$result = Audio::getInfos('/non/existent/file.mp3');
		$this->assertNull($result);
	}

	/* ===================== getFormat() ===================== */

	public function testGetFormatWithNonExistentFile(): void
	{
		$result = Audio::getFormat('/non/existent/file.mp3');
		$this->assertNull($result);
	}

	/* ===================== getDuration() ===================== */

	public function testGetDurationWithNonExistentFile(): void
	{
		$result = Audio::getDuration('/non/existent/file.mp3');
		$this->assertSame(0.0, $result);
	}

	/* ===================== normalizeIsmn() ===================== */

	public function testNormalizeIsmnRemovesHyphens(): void
	{
		$this->assertSame('9790230671187', Audio::normalizeIsmn('979-0-2306-7118-7'));
	}

	public function testNormalizeIsmnRemovesSpaces(): void
	{
		$this->assertSame('9790230671187', Audio::normalizeIsmn('979 0 2306 7118 7'));
	}

	public function testNormalizeIsmnTrimsWhitespace(): void
	{
		$this->assertSame('9790230671187', Audio::normalizeIsmn('  979-0-2306-7118-7  '));
	}

	/* ===================== isValidIsmn() ===================== */

	public function testCheckIsmnWithValidIsmn(): void
	{
		$this->assertTrue(Audio::isValidIsmn('979-0-2306-7118-7'));
		$this->assertTrue(Audio::isValidIsmn('9790230671187'));
		$this->assertTrue(Audio::isValidIsmn('979-0-001-01234-8'));
	}

	public function testCheckIsmnWithInvalidIsmn(): void
	{
		$this->assertFalse(Audio::isValidIsmn('invalid'));
		$this->assertFalse(Audio::isValidIsmn('123'));
		$this->assertFalse(Audio::isValidIsmn('9790000000000'));
	}

	public function testCheckIsmnWithEmptyString(): void
	{
		$this->assertFalse(Audio::isValidIsmn(''));
	}

	public function testCheckIsmnWithWrongPrefix(): void
	{
		// ISMN must start with 9790
		$this->assertFalse(Audio::isValidIsmn('978-0-2306-7118-7'));
		$this->assertFalse(Audio::isValidIsmn('9780230671187'));
	}

	public function testCheckIsmnWithWrongLength(): void
	{
		$this->assertFalse(Audio::isValidIsmn('97902306711'));
		$this->assertFalse(Audio::isValidIsmn('97902306711870'));
	}

	/* ===================== getBitrate() ===================== */

	public function testGetBitrateWithNonExistentFile(): void
	{
		$result = Audio::getBitrate('/non/existent/file.mp3');
		$this->assertNull($result);
	}

	/* ===================== getSampleRate() ===================== */

	public function testGetSampleRateWithNonExistentFile(): void
	{
		$result = Audio::getSampleRate('/non/existent/file.mp3');
		$this->assertNull($result);
	}

	/* ===================== getChannels() ===================== */

	public function testGetChannelsWithNonExistentFile(): void
	{
		$result = Audio::getChannels('/non/existent/file.mp3');
		$this->assertNull($result);
	}

	/* ===================== getArtist() ===================== */

	public function testGetArtistWithNonExistentFile(): void
	{
		$result = Audio::getArtist('/non/existent/file.mp3');
		$this->assertNull($result);
	}

	/* ===================== getTitle() ===================== */

	public function testGetTitleWithNonExistentFile(): void
	{
		$result = Audio::getTitle('/non/existent/file.mp3');
		$this->assertNull($result);
	}

	/* ===================== getAlbum() ===================== */

	public function testGetAlbumWithNonExistentFile(): void
	{
		$result = Audio::getAlbum('/non/existent/file.mp3');
		$this->assertNull($result);
	}

	/* ===================== getYear() ===================== */

	public function testGetYearWithNonExistentFile(): void
	{
		$result = Audio::getYear('/non/existent/file.mp3');
		$this->assertNull($result);
	}

	/* ===================== getTags() ===================== */

	public function testGetTagsWithNonExistentFile(): void
	{
		$result = Audio::getTags('/non/existent/file.mp3');
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	public function testGetTagsStructure(): void
	{
		// Test that getTags returns the expected array structure even with non-existent file
		$result = Audio::getTags('/non/existent/file.mp3');
		$this->assertIsArray($result);
		// Structure should be empty array when file doesn't exist
	}

	/* ===================== checkFile() ===================== */

	public function testCheckFileWithNonExistentFile(): void
	{
		$result = Audio::checkFile('/non/existent/file.mp3', 'test.mp3');
		$this->assertFalse($result);
	}

	/* ===================== checkMp3File() ===================== */

	public function testCheckMp3FileWithNonExistentFile(): void
	{
		$result = Audio::checkMp3File('/non/existent/file.mp3', 'test.mp3');
		$this->assertFalse($result);
	}

	/* ===================== checkWavFile() ===================== */

	public function testCheckWavFileWithNonExistentFile(): void
	{
		$result = Audio::checkWavFile('/non/existent/file.wav', 'test.wav');
		$this->assertFalse($result);
	}

	/* ===================== HTTP Response Methods ===================== */

	public function testGetHttpResponseWithNonExistentFile(): void
	{
		$response = Audio::getHttpResponse('/non/existent/file.mp3', false);

		$this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
		$this->assertEquals(400, $response->getStatusCode());
		$this->assertEquals('file_not_found', $response->getContent());
	}

	/* ===================== Edge Cases ===================== */

	public function testCheckFileWithEmptyFilePath(): void
	{
		$result = Audio::checkFile('', 'test.mp3');

		$this->assertFalse($result);
	}

	/* ===================== Real Audio Files Tests with Fixtures ===================== */

	public function testGetFormatWithRealWavFile(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		$result = Audio::getFormat($wavFile);

		$this->assertSame(Audio::WAV_FORMAT, $result);
	}

	public function testGetFormatWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getFormat($mp3File);

		$this->assertSame(Audio::MP3_FORMAT, $result);
	}

	public function testGetInfosWithRealWavFile(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		$result = Audio::getInfos($wavFile);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('fileformat', $result);
		$this->assertArrayHasKey('audio', $result);
	}

	public function testGetInfosWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getInfos($mp3File);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('fileformat', $result);
		$this->assertArrayHasKey('audio', $result);
	}

	public function testGetDurationWithRealWavFile(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		$result = Audio::getDuration($wavFile);

		$this->assertIsFloat($result);
		$this->assertGreaterThan(0.0, $result);
		$this->assertLessThan(1.0, $result); // Should be around 0.125 seconds
	}

	public function testGetDurationWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getDuration($mp3File);

		$this->assertIsFloat($result);
		$this->assertGreaterThan(0.0, $result);
	}

	public function testGetBitrateWithRealWavFile(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		$result = Audio::getBitrate($wavFile);

		$this->assertIsInt($result);
		$this->assertGreaterThan(0, $result);
	}

	public function testGetBitrateWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getBitrate($mp3File);

		$this->assertIsInt($result);
		$this->assertEquals(128000, $result); // 128kbps
	}

	public function testGetSampleRateWithRealWavFile(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		$result = Audio::getSampleRate($wavFile);

		$this->assertIsInt($result);
		$this->assertEquals(8000, $result);
	}

	public function testGetSampleRateWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getSampleRate($mp3File);

		$this->assertIsInt($result);
		$this->assertEquals(44100, $result);
	}

	public function testGetChannelsWithRealWavFile(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		$result = Audio::getChannels($wavFile);

		$this->assertIsInt($result);
		$this->assertEquals(1, $result); // Mono
	}

	public function testGetChannelsWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getChannels($mp3File);

		$this->assertIsInt($result);
		$this->assertEquals(2, $result); // Stereo
	}

	public function testGetTagsWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getTags($mp3File);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('artist', $result);
		$this->assertArrayHasKey('title', $result);
		$this->assertArrayHasKey('album', $result);
		$this->assertArrayHasKey('year', $result);
		$this->assertArrayHasKey('genre', $result);
		$this->assertArrayHasKey('comment', $result);
		$this->assertArrayHasKey('track_number', $result);
	}

	public function testGetTitleWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getTitle($mp3File);

		// The file has a title tag
		$this->assertIsString($result);
		$this->assertStringContainsString('Test', $result);
	}

	public function testCheckFileWithRealWavFile(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		$result = Audio::checkFile($wavFile, 'test.wav');

		$this->assertTrue($result);
	}

	public function testCheckFileWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::checkFile($mp3File, 'test.mp3');

		$this->assertTrue($result);
	}

	public function testCheckMp3FileWithRealMp3(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::checkMp3File($mp3File, 'test.mp3');

		$this->assertTrue($result);
	}

	public function testCheckMp3FileWithRealWavFile(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		// WAV file should not pass MP3 check
		$result = Audio::checkMp3File($wavFile, 'test.wav');

		$this->assertFalse($result);
	}

	public function testCheckWavFileWithRealWavFile(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		$result = Audio::checkWavFile($wavFile, 'test.wav');

		$this->assertTrue($result);
	}

	public function testCheckWavFileWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		// MP3 file should not pass WAV check
		$result = Audio::checkWavFile($mp3File, 'test.mp3');

		$this->assertFalse($result);
	}

	public function testGetHttpResponseWithRealMp3File(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$response = Audio::getHttpResponse($mp3File, false);

		$this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertNotEmpty($response->getContent());
		$this->assertGreaterThan(2000, strlen($response->getContent()));
	}

	public function testGetHttpResponseWithStreamModeAndRealFile(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		$response = Audio::getHttpResponse($mp3File, true);

		$this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertNotEmpty($response->getContent());
	}

	public function testCheckFileWithInvalidExtension(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		// File is WAV but we claim it's .ogg (not in allowed list)
		$result = Audio::checkFile($wavFile, 'test.ogg');

		$this->assertFalse($result);
	}

	public function testCheckMp3FileWithInvalidExtension(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		// File is MP3 but we claim it's .wav
		$result = Audio::checkMp3File($mp3File, 'test.wav');

		$this->assertFalse($result);
	}

	public function testCheckWavFileWithInvalidExtension(): void
	{
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		// File is WAV but we claim it's .mp3
		$result = Audio::checkWavFile($wavFile, 'test.mp3');

		$this->assertFalse($result);
	}

	/* ===================== Tests with Complete ID3 Tags ===================== */

	public function testGetArtistWithCompleteTags(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_with_tags.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getArtist($mp3File);

		$this->assertIsString($result);
		$this->assertEquals('Test Artist', $result);
	}

	public function testGetTitleWithCompleteTags(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_with_tags.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getTitle($mp3File);

		$this->assertIsString($result);
		$this->assertEquals('Test Title', $result);
	}

	public function testGetAlbumWithCompleteTags(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_with_tags.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getAlbum($mp3File);

		$this->assertIsString($result);
		$this->assertEquals('Test Album', $result);
	}

	public function testGetYearWithCompleteTags(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_with_tags.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getYear($mp3File);

		$this->assertIsString($result);
		$this->assertEquals('2024', $result);
	}

	public function testGetTagsWithCompleteTags(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_with_tags.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getTags($mp3File);

		$this->assertIsArray($result);
		$this->assertEquals('Test Artist', $result['artist']);
		$this->assertEquals('Test Title', $result['title']);
		$this->assertEquals('Test Album', $result['album']);
		$this->assertEquals('2024', $result['year']);
		$this->assertEquals('Rock', $result['genre']);
		$this->assertEquals('Test Comment', $result['comment']);
		$this->assertEquals('5', $result['track_number']);
	}

	public function testGetFormatWithCompleteTaggedFile(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_with_tags.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::getFormat($mp3File);

		$this->assertSame(Audio::MP3_FORMAT, $result);
	}

	public function testCheckMp3FileWithCompleteTaggedFile(): void
	{
		$mp3File = __DIR__ . '/../fixtures/audio/test_with_tags.mp3';
		$this->assertFileExists($mp3File);

		$result = Audio::checkMp3File($mp3File, 'music.mp3');

		$this->assertTrue($result);
	}

	/* ===================== Additional Edge Cases ===================== */

	public function testGetFormatWithEmptyInfos(): void
	{
		// Create an invalid file that getID3 can't parse
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid audio data');

		$result = Audio::getFormat($tempFile);

		$this->assertNull($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testCheckFileWithWrongFormat(): void
	{
		// Create a file that has wrong format
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'RIFF....WAVEfmt '); // WAV signature

		$result = Audio::checkFile($tempFile, 'test.mp3');

		$this->assertFalse($result); // Should fail because format doesn't match

		// Cleanup
		unlink($tempFile);

		// Real files whose extension does not match the detected format are rejected
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFalse(Audio::checkFile($wavFile, 'test.mp3'));
		$this->assertFalse(Audio::checkFile($mp3File, 'test.wav'));

		// Matching extension is accepted regardless of case
		$this->assertTrue(Audio::checkFile($wavFile, 'TEST.WAV'));
		$this->assertTrue(Audio::checkFile($mp3File, 'TEST.MP3'));

		// Alternative extensions of the detected format are accepted
		$this->assertTrue(Audio::checkFile($mp3File, 'test.mpga'));
	}

	public function testGetDurationReturnsZeroForInvalidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid');

		$result = Audio::getDuration($tempFile);

		$this->assertSame(0.0, $result);

		// Cleanup
		unlink($tempFile);
	}

	public function testGetBitrateReturnsNullForInvalidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid');

		$result = Audio::getBitrate($tempFile);

		$this->assertNull($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testGetSampleRateReturnsNullForInvalidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid');

		$result = Audio::getSampleRate($tempFile);

		$this->assertNull($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testGetChannelsReturnsNullForInvalidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid');

		$result = Audio::getChannels($tempFile);

		$this->assertNull($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testGetTagsReturnsEmptyArrayForInvalidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid');

		$result = Audio::getTags($tempFile);

		$this->assertIsArray($result);
		// getTags() returns an array with keys but null values for invalid files
		$this->assertNull($result['artist']);
		$this->assertNull($result['title']);
		$this->assertNull($result['album']);
		$this->assertNull($result['year']);
		$this->assertNull($result['genre']);
		$this->assertNull($result['comment']);
		$this->assertNull($result['track_number']);

		// Cleanup
		unlink($tempFile);
	}

	public function testGetArtistReturnsNullForInvalidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid');

		$result = Audio::getArtist($tempFile);

		$this->assertNull($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testGetTitleReturnsNullForInvalidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid');

		$result = Audio::getTitle($tempFile);

		$this->assertNull($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testGetAlbumReturnsNullForInvalidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid');

		$result = Audio::getAlbum($tempFile);

		$this->assertNull($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testGetYearReturnsNullForInvalidFile(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.mp3';
		file_put_contents($tempFile, 'invalid');

		$result = Audio::getYear($tempFile);

		$this->assertNull($result);

		// Cleanup
		unlink($tempFile);
	}

	public function testCheckMp3FileFailsWhenFormatDoesNotMatch(): void
	{
		// Create a WAV file
		$wavFile = __DIR__ . '/../fixtures/audio/test_mono_8000hz.wav';
		$this->assertFileExists($wavFile);

		// Try to check it as MP3 with correct extension
		$result = Audio::checkMp3File($wavFile, 'audio.mp3');

		$this->assertFalse($result);
	}

	public function testCheckWavFileFailsWhenFormatDoesNotMatch(): void
	{
		// Create an MP3 file
		$mp3File = __DIR__ . '/../fixtures/audio/test_stereo_128kbps.mp3';
		$this->assertFileExists($mp3File);

		// Try to check it as WAV with correct extension
		$result = Audio::checkWavFile($mp3File, 'audio.wav');

		$this->assertFalse($result);
	}

	public function testCheckIsmnWithValidIsmnVariations(): void
	{
		// Test multiple valid ISMN formats
		$this->assertTrue(Audio::isValidIsmn('979-0-2600-0043-8'));
		$this->assertTrue(Audio::isValidIsmn('9790260000438'));
		$this->assertTrue(Audio::isValidIsmn('979 0 2600 0043 8'));
		$this->assertTrue(Audio::isValidIsmn('  9790260000438  '));
	}

	public function testCheckIsmnWithInvalidChecksumDigit(): void
	{
		// Valid format but wrong checksum
		$this->assertFalse(Audio::isValidIsmn('979-0-2306-7118-8')); // Should be 7, not 8
		$this->assertFalse(Audio::isValidIsmn('9790230671188'));
	}
}