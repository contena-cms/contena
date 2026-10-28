<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\CustomerSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CustomerSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var EntityRepository<CustomerGroupCollection>
     */
    private EntityRepository $customerGroupRepository;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private CustomerSerializer $serializer;

    private string $customerGroupId = 'a536fe4ef675470f8cddfcc7f8360e4b';

    protected function setUp(): void
    {
        $this->customerGroupRepository = static::getContainer()->get('customer_group.repository');
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $serializerRegistry = static::getContainer()->get(SerializerRegistry::class);

        $this->serializer = new CustomerSerializer(
            $this->customerGroupRepository,
            $this->salesChannelRepository
        );
        $this->serializer->setRegistry($serializerRegistry);
    }

    public function testSimple(): void
    {
        $salesChannel = $this->createSalesChannel();
        $this->createCustomerGroup();

        $config = new Config([], [], []);
        $customer = [
            'group' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'test customer group',
                    ],
                ],
            ],
            'salesChannel' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => $salesChannel['name'],
                    ],
                ],
            ],
            'boundSalesChannel' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => $salesChannel['name'],
                    ],
                ],
            ],
        ];

        $deserialized = $this->serializer->deserialize($config, $this->customerRepository->getDefinition(), $customer);

        static::assertIsNotArray($deserialized);

        $deserialized = \iterator_to_array($deserialized);

        static::assertSame($this->customerGroupId, $deserialized['group']['id']);
        static::assertSame($salesChannel['id'], $deserialized['salesChannel']['id']);
        static::assertSame($salesChannel['id'], $deserialized['boundSalesChannel']['id']);
    }

    public function testSupportsOnlyCountry(): void
    {
        $serializer = new CustomerSerializer(
            $this->customerGroupRepository,
            $this->salesChannelRepository
        );

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            if ($entity === CustomerDefinition::ENTITY_NAME) {
                static::assertTrue($serializer->supports($entity));
            } else {
                static::assertFalse(
                    $serializer->supports($entity),
                    CustomerDefinition::class . ' should not support ' . $entity
                );
            }
        }
    }

    private function createCustomerGroup(): void
    {
        $this->customerGroupRepository->upsert([
            [
                'id' => $this->customerGroupId,
                'name' => 'test customer group',
            ],
        ], Context::createDefaultContext());
    }
}
