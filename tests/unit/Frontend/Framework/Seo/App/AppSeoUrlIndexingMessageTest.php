<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Struct\Serializer\StructNormalizer;
use Contena\Core\Test\Assert\Serialization;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlIndexingMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

/**
 * @internal
 */
#[CoversClass(AppSeoUrlIndexingMessage::class)]
class AppSeoUrlIndexingMessageTest extends TestCase
{
    private const BLOG_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_BLOG_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const BLOG_ID = 'cccccccccccccccccccccccccccccccc';

    public function testTheWrittenIdsAreKeptPerEntity(): void
    {
        $message = $this->message();

        static::assertSame(
            ['blog' => [self::BLOG_ID, self::OTHER_BLOG_ID], 'ce_blog' => [self::BLOG_ID]],
            $message->getIdsByEntity()
        );
        static::assertSame([self::BLOG_ID, self::OTHER_BLOG_ID], $message->getIds('blog'));
        static::assertSame([self::BLOG_ID], $message->getIds('ce_blog'));
    }

    public function testAnEntityWithoutWrittenIdsHasNoIds(): void
    {
        static::assertSame([], $this->message()->getIds('category'));
    }

    public function testWithoutIdsByEntityNoEntityHasIds(): void
    {
        $message = new AppSeoUrlIndexingMessage([self::BLOG_ID]);

        static::assertSame([], $message->getIdsByEntity());
        static::assertSame([], $message->getIds('blog'));
    }

    public function testTheIdsByEntitySurviveThePhpSerialization(): void
    {
        $message = $this->message();

        $restored = Serialization::assertRoundTrip($message);

        static::assertSame($message->getIdsByEntity(), $restored->getIdsByEntity());
        static::assertSame($message->getData(), $restored->getData());
    }

    public function testTheIdsByEntitySurviveTheJsonTransportOfTheMessageQueue(): void
    {
        $message = $this->message();
        $message->setIndexer('app_seo_url.indexer');
        $serializer = $this->messengerSerializer();

        $restored = $serializer->decode($serializer->encode(new Envelope($message)))->getMessage();

        static::assertInstanceOf(AppSeoUrlIndexingMessage::class, $restored);
        static::assertSame($message->getIdsByEntity(), $restored->getIdsByEntity());
        static::assertSame($message->getData(), $restored->getData());
        static::assertSame('app_seo_url.indexer', $restored->getIndexer());
        static::assertEquals($message->getContext(), $restored->getContext());
    }

    private function message(): AppSeoUrlIndexingMessage
    {
        $message = new AppSeoUrlIndexingMessage(
            [self::BLOG_ID, self::OTHER_BLOG_ID, self::BLOG_ID],
            null,
            Context::createDefaultContext()
        );
        $message->setIdsByEntity([
            'blog' => [self::BLOG_ID, self::OTHER_BLOG_ID],
            'ce_blog' => [self::BLOG_ID],
        ]);

        return $message;
    }

    private function messengerSerializer(): Serializer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        return new Serializer(new SymfonySerializer(
            [
                new StructNormalizer(),
                new ArrayDenormalizer(),
                new ObjectNormalizer(
                    classMetadataFactory: $classMetadataFactory,
                    propertyTypeExtractor: new ReflectionExtractor(),
                    classDiscriminatorResolver: new ClassDiscriminatorFromClassMetadata($classMetadataFactory),
                ),
            ],
            [new JsonEncoder()]
        ));
    }
}
