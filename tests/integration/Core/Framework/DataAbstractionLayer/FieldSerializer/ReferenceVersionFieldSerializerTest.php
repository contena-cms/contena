<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ReferenceVersionFieldSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUpdateSerialize(): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(100)
            ->manufacturer('m1')
            ->build();

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $connection = static::getContainer()->get(Connection::class);

        $value = $connection->fetchOne('SELECT LOWER(HEX(blog_manufacturer_version_id)) FROM blog WHERE id = :id', ['id' => $ids->getBytes('p1')]);
        static::assertSame(Defaults::LIVE_VERSION, $value);

        $connection->executeStatement('UPDATE blog SET blog_manufacturer_version_id = NULL WHERE id = :id', ['id' => $ids->getBytes('p1')]);

        $value = $connection->fetchOne('SELECT LOWER(HEX(blog_manufacturer_version_id)) FROM blog WHERE id = :id', ['id' => $ids->getBytes('p1')]);
        static::assertNull($value);

        $update = [
            'id' => $ids->get('p1'),
            'manufacturerId' => $ids->get('m1'),
        ];

        static::getContainer()->get('blog.repository')
            ->update([$update], Context::createDefaultContext());

        $value = $connection->fetchOne('SELECT LOWER(HEX(blog_manufacturer_version_id)) FROM blog WHERE id = :id', ['id' => $ids->getBytes('p1')]);
        static::assertSame(Defaults::LIVE_VERSION, $value);
    }
}
