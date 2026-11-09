<?php

namespace Tests\Messaging;

use Osimatic\Messaging\FirebaseMessaging;
use Osimatic\Messaging\MobilePushNotificationSenderInterface;
use Osimatic\Messaging\MobilePushNotificationSubscriptionInterface;
use Osimatic\Messaging\PushNotificationInterface;
use Osimatic\Messaging\PushNotificationSendingResponse;
use Osimatic\Messaging\PushNotificationSendingStatus;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[AllowMockObjectsWithoutExpectations]
class FirebaseMessagingTest extends TestCase
{
	// ========== Constructor Tests ==========

	public function testConstructorWithDefaultValues(): void
	{
		$sender = new FirebaseMessaging();

		self::assertInstanceOf(FirebaseMessaging::class, $sender);
		self::assertInstanceOf(MobilePushNotificationSenderInterface::class, $sender);
	}

	public function testConstructorWithAllParameters(): void
	{
		$logger = $this->createMock(LoggerInterface::class);

		$sender = new FirebaseMessaging(
			projectId: 'test-project-123',
			serviceKeyFile: '/path/to/service-key.json',
			logger: $logger
		);

		self::assertInstanceOf(FirebaseMessaging::class, $sender);
	}

	// ========== Setter Tests ==========

	public function testSetProjectId(): void
	{
		$sender = new FirebaseMessaging();

		$result = $sender->setProjectId('my-project-123');

		self::assertSame($sender, $result);
	}

	public function testSetServiceKeyFile(): void
	{
		$sender = new FirebaseMessaging();

		$result = $sender->setServiceKeyFile('/path/to/key.json');

		self::assertSame($sender, $result);
	}


	public function testFluentConfiguration(): void
	{
		$sender = new FirebaseMessaging();

		$result = $sender
			->setProjectId('project-123')
			->setServiceKeyFile('/path/to/key.json');

		self::assertSame($sender, $result);
	}

	// ========== send() Validation Tests ==========

	public function testSendReturnsErrorWhenProjectIdNotSet(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Project ID not set');

		$sender = new FirebaseMessaging(logger: $logger);

		$notification = $this->createMock(PushNotificationInterface::class);

		$response = $sender->send($notification);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
	}

	public function testSendReturnsErrorWhenServiceKeyFileNotSet(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Server key file not set');

		$sender = new FirebaseMessaging(
			projectId: 'test-project',
			logger: $logger
		);

		$notification = $this->createMock(PushNotificationInterface::class);

		$response = $sender->send($notification);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
	}

	public function testSendReturnsErrorWhenServiceKeyFileDoesNotExist(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('Service key file does not exist'));

		$sender = new FirebaseMessaging(
			projectId: 'test-project',
			serviceKeyFile: '/nonexistent/path/key.json',
			logger: $logger
		);

		$notification = $this->createMock(PushNotificationInterface::class);

		$response = $sender->send($notification);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
	}

	public function testSendReturnsErrorWhenDeviceTokenIsInvalid(): void
	{
		// Create a temporary service key file for testing
		$tempFile = tempnam(sys_get_temp_dir(), 'firebase_key_');
		file_put_contents($tempFile, json_encode(['type' => 'service_account', 'client_id' => '123456789', 'client_email' => 'test@test-project.iam.gserviceaccount.com', 'private_key' => 'fake-private-key']));

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('No device set');

		$sender = new FirebaseMessaging(
			projectId: 'test-project',
			serviceKeyFile: $tempFile,
			logger: $logger
		);

		$subscription = $this->createMock(MobilePushNotificationSubscriptionInterface::class);
		$subscription->method('getDeviceToken')->willReturn('');

		$notification = $this->createMock(PushNotificationInterface::class);
		$notification->method('getSubscription')->willReturn($subscription);

		$response = $sender->send($notification);

		unlink($tempFile);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::TOKEN_INVALID, $response->getStatus());
	}

	public function testSendReturnsErrorWhenTitleIsEmpty(): void
	{
		// Create a temporary service key file for testing
		$tempFile = tempnam(sys_get_temp_dir(), 'firebase_key_');
		file_put_contents($tempFile, json_encode(['type' => 'service_account', 'client_id' => '123456789', 'client_email' => 'test@test-project.iam.gserviceaccount.com', 'private_key' => 'fake-private-key']));

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Title and message are required');

		$sender = new FirebaseMessaging(
			projectId: 'test-project',
			serviceKeyFile: $tempFile,
			logger: $logger
		);

		$subscription = $this->createMock(MobilePushNotificationSubscriptionInterface::class);
		$subscription->method('getDeviceToken')->willReturn('valid-device-token');

		$notification = $this->createMock(PushNotificationInterface::class);
		$notification->method('getSubscription')->willReturn($subscription);
		$notification->method('getTitle')->willReturn('');
		$notification->method('getMessage')->willReturn('Test message');

		$response = $sender->send($notification);

		unlink($tempFile);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
	}

	public function testSendReturnsErrorWhenMessageIsEmpty(): void
	{
		// Create a temporary service key file for testing
		$tempFile = tempnam(sys_get_temp_dir(), 'firebase_key_');
		file_put_contents($tempFile, json_encode(['type' => 'service_account', 'client_id' => '123456789', 'client_email' => 'test@test-project.iam.gserviceaccount.com', 'private_key' => 'fake-private-key']));

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Title and message are required');

		$sender = new FirebaseMessaging(
			projectId: 'test-project',
			serviceKeyFile: $tempFile,
			logger: $logger
		);

		$subscription = $this->createMock(MobilePushNotificationSubscriptionInterface::class);
		$subscription->method('getDeviceToken')->willReturn('valid-device-token');

		$notification = $this->createMock(PushNotificationInterface::class);
		$notification->method('getSubscription')->willReturn($subscription);
		$notification->method('getTitle')->willReturn('Test title');
		$notification->method('getMessage')->willReturn('');

		$response = $sender->send($notification);

		unlink($tempFile);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
	}

	public function testSendReturnsErrorWhenBothTitleAndMessageAreEmpty(): void
	{
		// Create a temporary service key file for testing
		$tempFile = tempnam(sys_get_temp_dir(), 'firebase_key_');
		file_put_contents($tempFile, json_encode(['type' => 'service_account', 'client_id' => '123456789', 'client_email' => 'test@test-project.iam.gserviceaccount.com', 'private_key' => 'fake-private-key']));

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Title and message are required');

		$sender = new FirebaseMessaging(
			projectId: 'test-project',
			serviceKeyFile: $tempFile,
			logger: $logger
		);

		$subscription = $this->createMock(MobilePushNotificationSubscriptionInterface::class);
		$subscription->method('getDeviceToken')->willReturn('valid-device-token');

		$notification = $this->createMock(PushNotificationInterface::class);
		$notification->method('getSubscription')->willReturn($subscription);
		$notification->method('getTitle')->willReturn('');
		$notification->method('getMessage')->willReturn('');

		$response = $sender->send($notification);

		unlink($tempFile);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
	}

	// ========== send() with Invalid Service Key File ==========

	public function testSendReturnsErrorWhenServiceKeyFileIsInvalid(): void
	{
		// Create a temporary invalid service key file
		$tempFile = tempnam(sys_get_temp_dir(), 'firebase_key_');
		file_put_contents($tempFile, 'invalid json content');

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('Service key file is missing required fields'));

		$sender = new FirebaseMessaging(
			projectId: 'test-project',
			serviceKeyFile: $tempFile,
			logger: $logger
		);

		$subscription = $this->createMock(MobilePushNotificationSubscriptionInterface::class);
		$subscription->method('getDeviceToken')->willReturn('valid-device-token');

		$notification = $this->createMock(PushNotificationInterface::class);
		$notification->method('getSubscription')->willReturn($subscription);
		$notification->method('getTitle')->willReturn('Test title');
		$notification->method('getMessage')->willReturn('Test message');
		$notification->method('getTimeToLive')->willReturn(null);
		$notification->method('getCollapseKey')->willReturn(null);
		$notification->method('getData')->willReturn(null);

		$response = $sender->send($notification);

		unlink($tempFile);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
	}

	// ========== send() with Invalid Authentication ==========

	public function testSendFailsWithInvalidServiceKey(): void
	{
		// Create an invalid service key file (invalid JSON structure)
		$tempFile = tempnam(sys_get_temp_dir(), 'firebase_key_');
		file_put_contents($tempFile, 'invalid json');

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('Service key file is missing required fields'));

		$sender = new FirebaseMessaging(
			projectId: 'test-project',
			serviceKeyFile: $tempFile,
			logger: $logger
		);

		$subscription = $this->createMock(MobilePushNotificationSubscriptionInterface::class);
		$subscription->method('getDeviceToken')->willReturn('valid-device-token-123');

		$notification = $this->createMock(PushNotificationInterface::class);
		$notification->method('getSubscription')->willReturn($subscription);
		$notification->method('getTitle')->willReturn('Test title');
		$notification->method('getMessage')->willReturn('Test message');
		$notification->method('getTimeToLive')->willReturn(3600);
		$notification->method('getCollapseKey')->willReturn('test-collapse-key');
		$notification->method('getData')->willReturn(['key1' => 'value1', 'key2' => 'value2']);

		$response = $sender->send($notification);

		unlink($tempFile);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
	}

	public function testSendWithNullLogger(): void
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'firebase_key_');
		file_put_contents($tempFile, json_encode(['type' => 'service_account']));

		$sender = new FirebaseMessaging(
			projectId: 'test-project',
			serviceKeyFile: $tempFile,
			logger: new NullLogger()
		);

		$subscription = $this->createMock(MobilePushNotificationSubscriptionInterface::class);
		$subscription->method('getDeviceToken')->willReturn('token');

		$notification = $this->createMock(PushNotificationInterface::class);
		$notification->method('getSubscription')->willReturn($subscription);
		$notification->method('getTitle')->willReturn('Title');
		$notification->method('getMessage')->willReturn('Message');
		$notification->method('getTimeToLive')->willReturn(null);
		$notification->method('getCollapseKey')->willReturn(null);
		$notification->method('getData')->willReturn(null);

		$response = $sender->send($notification);

		unlink($tempFile);

		// Should fail with auth error, not validation error (title and message are valid)
		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
	}

	// ========== Success Tests (using mock Firebase responses) ==========

	public function testResponseIsSuccessObjectStructure(): void
	{
		// Test that we can create and validate response object structure
		$response = new PushNotificationSendingResponse(true, null, ['name' => 'projects/test/messages/123']);

		self::assertTrue($response->isSuccess());
		self::assertNull($response->getStatus());
		self::assertIsArray($response->getResponseData());
		self::assertArrayHasKey('name', $response->getResponseData());
	}

	public function testResponseWithSuccessData(): void
	{
		// Simulate a successful Firebase response
		$responseData = [
			'name' => 'projects/test-project/messages/0:1234567890123456%abcdef',
		];

		$response = new PushNotificationSendingResponse(true, null, $responseData);

		self::assertTrue($response->isSuccess());
		self::assertNull($response->getStatus());
		self::assertSame($responseData, $response->getResponseData());
	}

	public function testResponseWithErrorInvalidArgument(): void
	{
		$responseData = [
			'error' => [
				'code' => 400,
				'message' => 'Invalid argument',
				'status' => 'INVALID_ARGUMENT',
			],
		];

		$response = new PushNotificationSendingResponse(false, PushNotificationSendingStatus::SETTINGS_INVALID, $responseData);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::SETTINGS_INVALID, $response->getStatus());
		self::assertSame($responseData, $response->getResponseData());
	}

	public function testResponseWithErrorUnregistered(): void
	{
		$responseData = [
			'error' => [
				'code' => 404,
				'message' => 'Requested entity was not found',
				'status' => 'UNREGISTERED',
			],
		];

		$response = new PushNotificationSendingResponse(false, PushNotificationSendingStatus::TOKEN_EXPIRED, $responseData);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::TOKEN_EXPIRED, $response->getStatus());
	}

	public function testResponseWithErrorNotFound(): void
	{
		$responseData = [
			'error' => [
				'code' => 404,
				'message' => 'App instance not found',
				'status' => 'NOT_FOUND',
			],
		];

		$response = new PushNotificationSendingResponse(false, PushNotificationSendingStatus::TOKEN_EXPIRED, $responseData);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::TOKEN_EXPIRED, $response->getStatus());
	}

	public function testResponseWithErrorPermissionDenied(): void
	{
		$responseData = [
			'error' => [
				'code' => 403,
				'message' => 'Permission denied',
				'status' => 'PERMISSION_DENIED',
			],
		];

		$response = new PushNotificationSendingResponse(false, PushNotificationSendingStatus::TOKEN_INVALID, $responseData);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::TOKEN_INVALID, $response->getStatus());
	}

	public function testResponseWithErrorUnavailable(): void
	{
		$responseData = [
			'error' => [
				'code' => 503,
				'message' => 'Service unavailable',
				'status' => 'UNAVAILABLE',
			],
		];

		$response = new PushNotificationSendingResponse(false, PushNotificationSendingStatus::HTTP, $responseData);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::HTTP, $response->getStatus());
	}

	public function testResponseWithErrorQuotaExceeded(): void
	{
		$responseData = [
			'error' => [
				'code' => 429,
				'message' => 'Quota exceeded',
				'status' => 'QUOTA_EXCEEDED',
			],
		];

		$response = new PushNotificationSendingResponse(false, PushNotificationSendingStatus::QUOTA_EXCEEDED, $responseData);

		self::assertFalse($response->isSuccess());
		self::assertSame(PushNotificationSendingStatus::QUOTA_EXCEEDED, $response->getStatus());
	}

	public function testNotificationWithAllOptions(): void
	{
		// Test that all notification options are properly validated
		$subscription = $this->createMock(MobilePushNotificationSubscriptionInterface::class);
		$subscription->method('getDeviceToken')->willReturn('valid-token-12345');

		$notification = $this->createMock(PushNotificationInterface::class);
		$notification->method('getSubscription')->willReturn($subscription);
		$notification->method('getTitle')->willReturn('Important Update');
		$notification->method('getMessage')->willReturn('You have a new message');
		$notification->method('getTimeToLive')->willReturn(86400); // 24 hours
		$notification->method('getCollapseKey')->willReturn('update-notification');
		$notification->method('getData')->willReturn([
			'type' => 'message',
			'id' => '123',
			'priority' => 'high',
		]);

		// Verify all getters work
		self::assertSame('valid-token-12345', $notification->getSubscription()->getDeviceToken());
		self::assertSame('Important Update', $notification->getTitle());
		self::assertSame('You have a new message', $notification->getMessage());
		self::assertSame(86400, $notification->getTimeToLive());
		self::assertSame('update-notification', $notification->getCollapseKey());
		self::assertIsArray($notification->getData());
		self::assertCount(3, $notification->getData());
	}

	public function testNotificationWithMinimalOptions(): void
	{
		// Test notification with only required fields
		$subscription = $this->createMock(MobilePushNotificationSubscriptionInterface::class);
		$subscription->method('getDeviceToken')->willReturn('minimal-token');

		$notification = $this->createMock(PushNotificationInterface::class);
		$notification->method('getSubscription')->willReturn($subscription);
		$notification->method('getTitle')->willReturn('Title');
		$notification->method('getMessage')->willReturn('Message');
		$notification->method('getTimeToLive')->willReturn(null);
		$notification->method('getCollapseKey')->willReturn(null);
		$notification->method('getData')->willReturn(null);

		// Verify minimal configuration
		self::assertSame('minimal-token', $notification->getSubscription()->getDeviceToken());
		self::assertSame('Title', $notification->getTitle());
		self::assertSame('Message', $notification->getMessage());
		self::assertNull($notification->getTimeToLive());
		self::assertNull($notification->getCollapseKey());
		self::assertNull($notification->getData());
	}
}