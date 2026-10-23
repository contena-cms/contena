<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class EntityWrittenEventSerializationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testEventCanBeSerialized(): void
    {
        $container = $this->writeTestBlog();
        $event = $container->getEventByEntityName(BlogDefinition::ENTITY_NAME);

        $encoded = json_encode($event, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($encoded);
        static::assertJson($encoded);

        $encoded = json_encode($container, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($encoded);
        static::assertJson($encoded);
    }

    private function writeTestBlog(): EntityWrittenContainerEvent
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');

        return $blogRepository->create(
            [[
                'id' => Uuid::randomHex(),
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand',
                ],
                'name' => 'wusel',
                'blogNumber' => Uuid::randomHex(),
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                'stock' => 0,
            ]],
            Context::createDefaultContext()
        );
    }
}
