<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Product\ProductFeatureSet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('inventory')]
class ProductFeatureSetCrudTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSetNullOnDelete(): void
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->create('product'),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'featureSet' => [
                'id' => $ids->create('feature-set'),
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Test feature set',
                        'description' => 'Lorem ipsum dolor sit amet',
                    ],
                ],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $exists = static::getContainer()
            ->get(Connection::class)
            ->fetchOne(
                'SELECT id FROM product_feature_set WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('feature-set'))]
            );

        static::assertSame($exists, Uuid::fromHexToBytes($ids->get('feature-set')));

        $delete = ['id' => $ids->get('feature-set')];

        static::getContainer()->get('product_feature_set.repository')
            ->delete([$delete], Context::createDefaultContext());

        $exists = static::getContainer()
            ->get(Connection::class)
            ->fetchOne(
                'SELECT id FROM product_feature_set WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('feature-set'))]
            );

        static::assertFalse($exists);

        $foreignKey = static::getContainer()
            ->get(Connection::class)
            ->fetchOne(
                'SELECT product_feature_set_id FROM product WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('product'))]
            );

        static::assertNull($foreignKey);
    }

    public function testNameIsRequired(): void
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->create('feature-set'),
        ];

        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('This value should not be blank.', null, [], '', null, null),
            ]),
        ));

        try {
            static::getContainer()->get('product_feature_set.repository')
                ->create([$data], Context::createDefaultContext());
        } catch (WriteException $e) {
            foreach ($e->getExceptions() as $inner) {
                throw $inner;
            }

            throw $e;
        }
    }
}
