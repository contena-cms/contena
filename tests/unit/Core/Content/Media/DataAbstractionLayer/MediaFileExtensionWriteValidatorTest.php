<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\DataAbstractionLayer\MediaFileExtensionWriteValidator;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(MediaFileExtensionWriteValidator::class)]
class MediaFileExtensionWriteValidatorTest extends TestCase
{
    private WriteContext $context;

    private MediaDefinition $mediaDefinition;

    private ChannelDefinition $channelDefinition;

    protected function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());

        $registry = new StaticDefinitionInstanceRegistry(
            [MediaDefinition::class, ChannelDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $mediaDefinition = $registry->get(MediaDefinition::class);
        static::assertInstanceOf(MediaDefinition::class, $mediaDefinition);
        $this->mediaDefinition = $mediaDefinition;

        $channelDefinition = $registry->get(ChannelDefinition::class);
        static::assertInstanceOf(ChannelDefinition::class, $channelDefinition);
        $this->channelDefinition = $channelDefinition;
    }

    public function testSubscribedEvents(): void
    {
        $events = MediaFileExtensionWriteValidator::getSubscribedEvents();

        static::assertSame(['preValidate'], array_values($events));
        static::assertArrayHasKey(PreWriteValidationEvent::class, $events);
    }

    public function testIgnoresOtherEntities(): void
    {
        $command = new InsertCommand(
            $this->channelDefinition,
            ['file_extension' => '/../../../../foo.php'],
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0/'
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        $this->createValidator()->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresDeleteCommands(): void
    {
        $command = new DeleteCommand(
            $this->mediaDefinition,
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class)
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        $this->createValidator()->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testPayloadWithoutFileExtensionPasses(): void
    {
        $command = new UpdateCommand(
            $this->mediaDefinition,
            ['file_name' => 'foo'],
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0/'
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        $this->createValidator()->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function publicAllowedExtensions(): iterable
    {
        yield 'public extension' => ['png'];
        yield 'uppercase is normalized' => ['PNG'];
        yield 'empty string is skipped' => [''];
        yield 'null is skipped' => [null];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function publicForbiddenExtensions(): iterable
    {
        yield 'php extension' => ['php'];
        yield 'unknown extension' => ['exe'];
        yield 'private-only extension not allowed on public media' => ['zip'];
    }

    #[DataProvider('publicAllowedExtensions')]
    public function testAllowedExtensionOnPublicMediaPasses(?string $extension): void
    {
        $event = $this->dispatchUpdate($extension, ['private' => false]);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[DataProvider('publicForbiddenExtensions')]
    public function testForbiddenExtensionOnPublicMediaIsRejected(string $extension): void
    {
        $event = $this->dispatchUpdate($extension, ['private' => false]);

        $this->assertRejected($event, $extension);
    }

    public function testPrivateOnlyExtensionAllowedWhenMediaIsPrivateInPayload(): void
    {
        $event = $this->dispatchUpdate('zip', ['private' => true]);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testPrivateOnlyExtensionAllowedWhenStoredMediaIsPrivate(): void
    {
        // No `private` in the payload, so the validator resolves the visibility from the stored row.
        $event = $this->dispatchUpdate('zip', [], isStoredPrivate: true);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testPrivateOnlyExtensionRejectedWhenStoredMediaIsPublic(): void
    {
        $event = $this->dispatchUpdate('zip', [], isStoredPrivate: false);

        $this->assertRejected($event, 'zip');
    }

    public function testInsertWithoutPrivateFlagDefaultsToPublicWithoutDatabaseLookup(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $validator = new MediaFileExtensionWriteValidator(
            new MediaFileExtensionListProvider(new EventDispatcher(), ['png', 'jpg'], ['png', 'jpg', 'zip']),
            $connection
        );

        $command = new InsertCommand(
            $this->mediaDefinition,
            ['file_extension' => 'zip'],
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0/'
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        $validator->preValidate($event);

        $this->assertRejected($event, 'zip');
    }

    private function assertRejected(PreWriteValidationEvent $event, string $extension): void
    {
        $thrown = null;

        try {
            $event->getExceptions()->tryToThrow();
        } catch (WriteException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, \sprintf('Expected WriteException for extension "%s"', $extension));

        $violationException = $thrown->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $violationException);

        $violation = $violationException->getViolations()->get(0);
        static::assertSame('MEDIA_ILLEGAL_FILE_EXTENSION', $violation->getCode());
        static::assertSame('/fileExtension', $violation->getPropertyPath());
    }

    /**
     * @param array<string, mixed> $extraPayload
     */
    private function dispatchUpdate(?string $fileExtension, array $extraPayload, ?bool $isStoredPrivate = null): PreWriteValidationEvent
    {
        $id = Uuid::randomBytes();

        $command = new UpdateCommand(
            $this->mediaDefinition,
            ['file_extension' => $fileExtension, ...$extraPayload],
            ['id' => $id],
            static::createStub(EntityExistence::class),
            '/0/'
        );

        // The validator looks up the stored `private` flag keyed by the media's hex id.
        $storedMapping = $isStoredPrivate === null
            ? []
            : [Uuid::fromBytesToHex($id) => $isStoredPrivate ? '1' : '0'];

        $event = new PreWriteValidationEvent($this->context, [$command]);
        $this->createValidator($storedMapping)->preValidate($event);

        return $event;
    }

    /**
     * @param array<string, string> $storedPrivateMapping
     */
    private function createValidator(array $storedPrivateMapping = []): MediaFileExtensionWriteValidator
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($storedPrivateMapping);

        return new MediaFileExtensionWriteValidator(
            new MediaFileExtensionListProvider(new EventDispatcher(), ['png', 'jpg'], ['png', 'jpg', 'zip']),
            $connection
        );
    }
}
