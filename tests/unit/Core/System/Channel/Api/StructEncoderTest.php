<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Struct\Serializer\StructNormalizer;
use Contena\Core\System\Channel\Api\ResponseFields;
use Contena\Core\System\Channel\Api\StructEncoder;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\DefinitionRegistryChain;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(StructEncoder::class)]
class StructEncoderTest extends TestCase
{
    public function testNoneMappedFieldsAreNotExposed(): void
    {
        $blog = new ExtendedBlogEntity();
        $blog->internalSetEntityData(BlogDefinition::ENTITY_NAME, new FieldVisibility([]));
        $blog->setName('test');

        $encoded = $this->createStructEncoder()->encode($blog, new ResponseFields());

        static::assertArrayNotHasKey('notExposed', $encoded);
        static::assertSame('test', $encoded['name']);
    }

    public function testCustomFieldsAreExposed(): void
    {
        $blog = $this->createBlog();
        $blog->setCustomFields(['visible_1' => 'test', 'visible_2' => 'test']);

        $encoded = $this->createStructEncoder()->encode($blog, new ResponseFields());

        static::assertSame(['visible_1' => 'test', 'visible_2' => 'test'], $encoded['customFields']);
    }

    public function testCustomFieldsFieldIsBlocked(): void
    {
        $blog = $this->createBlog();
        $blog->setCustomFields(['visible' => 'test', 'blocked' => 'test']);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn([
            ['entity_name' => BlogDefinition::ENTITY_NAME, 'name' => 'blocked'],
        ]);

        $encoded = $this->createStructEncoder($connection)->encode($blog, new ResponseFields());

        static::assertSame(['visible' => 'test'], $encoded['customFields']);
    }

    public function testResetReloadsBlockedCustomFields(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData(BlogDefinition::ENTITY_NAME, new FieldVisibility([]));

        $blog->setName('test');
        $blog->setCustomFields(['visible' => 'test', 'blocked' => 'test']);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'entity_name' => BlogDefinition::ENTITY_NAME,
                    'name' => 'blocked',
                ],
            ]);

        $structEncoder = $this->createStructEncoder($connection);

        $expectedCustomFields = [
            'visible' => 'test',
        ];

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);

        $structEncoder->reset();

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);
    }

    public function testResponseFieldsEncodeIncludesAndExcludesCorrectly(): void
    {
        $blog = $this->createBlog();
        $blog->setId('1');
        $blog->setName('test');

        $responseFields = new ResponseFields(
            includes: [BlogDefinition::ENTITY_NAME => ['id', 'name']],
            excludes: [BlogDefinition::ENTITY_NAME => ['name']],
        );

        $encoded = $this->createStructEncoder()->encode($blog, $responseFields);

        static::assertSame([
            'id' => '1',
            'apiAlias' => BlogDefinition::ENTITY_NAME,
        ], $encoded);
    }

    private function createBlog(): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData(BlogDefinition::ENTITY_NAME, new FieldVisibility([]));
        $blog->setName('test');

        return $blog;
    }

    private function createStructEncoder(?Connection $connection = null): StructEncoder
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [BlogDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $channelRegistry = new ChannelDefinitionInstanceRegistry('', static::createStub(ContainerInterface::class), [], []);
        $serializer = new Serializer([new StructNormalizer()], [new JsonEncoder()]);

        return new StructEncoder(
            new DefinitionRegistryChain($registry, $channelRegistry),
            $serializer,
            $connection ?? static::createStub(Connection::class),
        );
    }
}

/**
 * @internal
 */
class ExtendedBlogEntity extends BlogEntity
{
    public string $notExposed = 'test';
}
