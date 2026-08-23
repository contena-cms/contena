<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\Message;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
class UpdateThumbnailsMessageTest extends TestCase
{
    use KernelTestBehaviour;

    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = static::getContainer()->get('serializer');
    }

    public function testDeserializationWithStrict(): void
    {
        $message = new UpdateThumbnailsMessage();
        $message->setStrict(true);

        $serialized = $this->serializer->serialize($message, 'json');
        $deserialized = $this->serializer->deserialize($serialized, UpdateThumbnailsMessage::class, 'json');

        static::assertInstanceOf(UpdateThumbnailsMessage::class, $deserialized);
        static::assertTrue($deserialized->isStrict());
    }

    public function testDeserializationDefaultsToNonStrict(): void
    {
        $message = new UpdateThumbnailsMessage();

        $serialized = $this->serializer->serialize($message, 'json');
        $deserialized = $this->serializer->deserialize($serialized, UpdateThumbnailsMessage::class, 'json');

        static::assertInstanceOf(UpdateThumbnailsMessage::class, $deserialized);
        static::assertFalse($deserialized->isStrict());
    }

    public function testDeserializationWithForce(): void
    {
        $message = new UpdateThumbnailsMessage();
        $message->setForce(true);

        $serialized = $this->serializer->serialize($message, 'json');
        $deserialized = $this->serializer->deserialize($serialized, UpdateThumbnailsMessage::class, 'json');

        static::assertInstanceOf(UpdateThumbnailsMessage::class, $deserialized);
        static::assertTrue($deserialized->isForce());
    }

    public function testDeserializationDefaultsToNonForce(): void
    {
        $message = new UpdateThumbnailsMessage();

        $serialized = $this->serializer->serialize($message, 'json');
        $deserialized = $this->serializer->deserialize($serialized, UpdateThumbnailsMessage::class, 'json');

        static::assertInstanceOf(UpdateThumbnailsMessage::class, $deserialized);
        static::assertFalse($deserialized->isForce());
    }
}
