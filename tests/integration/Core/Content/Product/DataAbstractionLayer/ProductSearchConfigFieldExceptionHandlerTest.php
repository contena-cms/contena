<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\Exception\DuplicateProductSearchConfigFieldException;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class ProductSearchConfigFieldExceptionHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDuplicateInsert(): void
    {
        static::getContainer()->get(Connection::class)
            ->executeStatement('DELETE FROM product_search_config');

        $ids = new IdsCollection();
        $config = [
            'id' => $ids->get('config'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'andLogic' => true,
            'minSearchLength' => 3,
            'configFields' => [
                ['id' => $ids->get('field-1'), 'field' => 'test'],
                ['id' => $ids->get('field-2'), 'field' => 'test'],
            ],
        ];

        $this->expectExceptionObject(new DuplicateProductSearchConfigFieldException('test', new \Exception()));

        static::getContainer()->get('product_search_config.repository')
            ->create([$config], Context::createDefaultContext());
    }
}
