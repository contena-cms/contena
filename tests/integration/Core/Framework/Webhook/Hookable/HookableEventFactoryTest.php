<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Event\UserBeforeLoginEvent;
use Contena\Core\Content\Flow\Dispatching\FlowFactory;
use Contena\Core\Content\Flow\Dispatching\FlowState;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\Hookable\HookableBusinessEvent;
use Contena\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class HookableEventFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private HookableEventFactory $hookableEventFactory;

    protected function setUp(): void
    {
        $this->hookableEventFactory = static::getContainer()->get(HookableEventFactory::class);
    }

    public function testDoesNotCreateEventForConcreteBusinessEvent(): void
    {
        $factory = static::getContainer()->get(FlowFactory::class);
        $event = $factory->create(new UserBeforeLoginEvent(
            static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL),
            'test@example.com'
        ));
        $event->setFlowState(new FlowState());
        $hookables = $this->hookableEventFactory->createHookablesFor($event);

        static::assertCount(0, $hookables);
    }

    public function testDoesCreateHookableBusinessEvent(): void
    {
        $hookables = $this->hookableEventFactory->createHookablesFor(
            new TestFlowBusinessEvent(Context::createDefaultContext())
        );

        static::assertCount(1, $hookables);
        static::assertInstanceOf(HookableBusinessEvent::class, $hookables[0]);
    }

    public function testCreatesHookableEntityInsert(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $writtenEvent = $this->insertBlog($id, $blogRepository);

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);
        $event = $hookables[0];
        static::assertSame('blog.written', $event->getName());

        $payload = $event->getWebhookPayload();
        static::assertCount(1, $payload);
        $actualUpdatedFields = $payload[0]['updatedFields'];
        unset($payload[0]['updatedFields']);

        static::assertSame([[
            'entity' => 'blog',
            'operation' => 'insert',
            'primaryKey' => $id,
            'versionId' => Defaults::LIVE_VERSION,
        ]], $payload);

        $expectedUpdatedFields = [
            'versionId',
            'id',
            'parentVersionId',
            'manufacturerId',
            'blogManufacturerVersionId',
            'blogMediaVersionId',
            'taxId',
            'stock',
            'price',
            'blogNumber',
            'isCloseout',
            'purchaseSteps',
            'minPurchase',
            'shippingFree',
            'restockTime',
            'createdAt',
            'name',
        ];

        foreach ($expectedUpdatedFields as $field) {
            static::assertContains($field, $actualUpdatedFields);
        }
    }

    public function testCreatesHookableEntityUpdate(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $this->insertBlog($id, $blogRepository);

        $writtenEvent = $blogRepository->upsert([
            [
                'id' => $id,
                'stock' => 99,
                'price' => [
                    [
                        'gross' => 200,
                        'net' => 250,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);
        $event = $hookables[0];
        static::assertSame('blog.written', $event->getName());

        $payload = $event->getWebhookPayload();
        $actualUpdatedFields = $payload[0]['updatedFields'];
        unset($payload[0]['updatedFields']);

        static::assertSame([[
            'entity' => 'blog',
            'operation' => 'update',
            'primaryKey' => $id,
            'versionId' => Defaults::LIVE_VERSION,
        ]], $payload);

        $expectedUpdatedFields = [
            'stock',
            'price',
            'updatedAt',
            'id',
            'versionId',
        ];

        foreach ($expectedUpdatedFields as $field) {
            static::assertContains($field, $actualUpdatedFields);
        }
    }

    public function testCreatesHookableEntityDelete(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $this->insertBlog($id, $blogRepository);

        $writtenEvent = $blogRepository->delete([['id' => $id]], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);
        $event = $hookables[0];
        static::assertSame('blog.deleted', $event->getName());
        static::assertSame([[
            'entity' => 'blog',
            'operation' => 'delete',
            'primaryKey' => $id,
            'versionId' => Defaults::LIVE_VERSION,
        ]], $event->getWebhookPayload());
    }

    public function testDoesNotCreateHookableNotHookableEntity(): void
    {
        $id = Uuid::randomHex();
        /** @var EntityRepository<TaxCollection> */
        $taxRepository = static::getContainer()->get('tax.repository');

        $createdEvent = $taxRepository->upsert([
            [
                'id' => $id,
                'name' => 'luxury',
                'taxRate' => '25',
            ],
        ], Context::createDefaultContext());

        static::assertCount(
            0,
            $this->hookableEventFactory->createHookablesFor($createdEvent)
        );

        $updatedEvent = $taxRepository->upsert([
            [
                'id' => $id,
                'name' => 'test update',
            ],
        ], Context::createDefaultContext());

        static::assertCount(
            0,
            $this->hookableEventFactory->createHookablesFor($updatedEvent)
        );

        $deletedEvent = $taxRepository->delete([['id' => $id]], Context::createDefaultContext());

        static::assertCount(
            0,
            $this->hookableEventFactory->createHookablesFor($deletedEvent)
        );
    }

    public function testCreatesEntityWriteForTranslationUpdate(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $this->insertBlog($id, $blogRepository);

        $writtenEvent = $blogRepository->upsert([
            [
                'id' => $id,
                'name' => 'a new name',
                'description' => 'a fancy description.',
            ],
        ], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);
        $event = $hookables[0];
        static::assertSame('blog.written', $event->getName());

        static::assertSame([[
            'entity' => 'blog',
            'operation' => 'update',
            'primaryKey' => $id,
            'updatedFields' => [
                'versionId',
                'parentVersionId',
                'blogManufacturerVersionId',
                'blogMediaVersionId',
                'canonicalBlogVersionId',
                'cmsPageVersionId',
                'updatedAt',
                'id',
                'name',
                'description',
                'descriptionTeaser',
            ],
            'versionId' => Defaults::LIVE_VERSION,
        ]], $event->getWebhookPayload());
    }

    public function testCreatesMultipleHookables(): void
    {
        $id = Uuid::randomHex();
        $blogPriceId = Uuid::randomHex();

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $this->insertBlog($id, $blogRepository);

        $ruleRepository = static::getContainer()->get('rule.repository');
        $ruleId = $ruleRepository->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        $writtenEvent = $blogRepository->upsert([
            [
                'id' => $id,
                'name' => 'a new name',
                'description' => 'a fancy description.',
                'prices' => [
                    [
                        'id' => $blogPriceId,
                        'ruleId' => $ruleId,
                        'quantityStart' => 1,
                        'price' => [
                            [
                                'gross' => 100,
                                'net' => 200,
                                'linked' => false,
                                'currencyId' => Defaults::CURRENCY,
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(2, $hookables);
        $event = $hookables[0];
        static::assertSame('blog.written', $event->getName());

        static::assertSame([[
            'entity' => 'blog',
            'operation' => 'update',
            'primaryKey' => $id,
            'updatedFields' => [
                'versionId',
                'parentVersionId',
                'blogManufacturerVersionId',
                'blogMediaVersionId',
                'canonicalBlogVersionId',
                'cmsPageVersionId',
                'updatedAt',
                'id',
                'name',
                'description',
                'descriptionTeaser',
            ],
            'versionId' => Defaults::LIVE_VERSION,
        ]], $event->getWebhookPayload());

        $event = $hookables[1];
        static::assertSame('blog_price.written', $event->getName());
        $payload = $event->getWebhookPayload();
        // the write-result field order differs between the feature-flag states; only the set is contractual
        sort($payload[0]['updatedFields']);
        static::assertSame([[
            'entity' => 'blog_price',
            'operation' => 'insert',
            'primaryKey' => $blogPriceId,
            'updatedFields' => [
                'createdAt',
                'id',
                'price',
                'blogId',
                'blogVersionId',
                'quantityStart',
                'ruleId',
                'versionId',
            ],
            'versionId' => Defaults::LIVE_VERSION,
        ]], $payload);
    }

    public function testDoesNotCreateMultipleHookablesForEmptyEvents(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $this->insertBlog($id, $blogRepository);

        $ruleRepository = static::getContainer()->get('rule.repository');
        $ruleId = $ruleRepository->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        /** @var EntityRepository<BlogPriceCollection> */
        $blogPriceRepository = static::getContainer()->get('blog_price.repository');
        $writtenEvent = $blogPriceRepository->upsert([
            [
                'id' => $id,
                'blogId' => $id,
                'ruleId' => $ruleId,
                'quantityStart' => 1,
                'price' => [
                    [
                        'gross' => 100,
                        'net' => 200,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);

        $event = $hookables[0];
        static::assertSame('blog_price.written', $event->getName());
        $payload = $event->getWebhookPayload();
        // the write-result field order differs between the feature-flag states; only the set is contractual
        sort($payload[0]['updatedFields']);
        static::assertSame([[
            'entity' => 'blog_price',
            'operation' => 'insert',
            'primaryKey' => $id,
            'updatedFields' => [
                'createdAt',
                'id',
                'price',
                'blogId',
                'blogVersionId',
                'quantityStart',
                'ruleId',
                'versionId',
            ],
            'versionId' => Defaults::LIVE_VERSION,
        ]], $payload);
    }

    public function testCreatesHookableEntityInsertWithoutVersionId(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository<ChannelDomainCollection> */
        $channelDomainRepository = static::getContainer()->get('channel_domain.repository');
        $writtenEvent = $this->insertChannelDomain($id, $channelDomainRepository);

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);
        $event = $hookables[0];
        static::assertSame('channel_domain.written', $event->getName());

        $payload = $event->getWebhookPayload();
        static::assertCount(1, $payload);
        $actualUpdatedFields = $payload[0]['updatedFields'];
        unset($payload[0]['updatedFields']);

        static::assertSame([[
            'entity' => 'channel_domain',
            'operation' => 'insert',
            'primaryKey' => $id,
        ]], $payload);

        $expectedUpdatedFields = [
            'id',
            'channelId',
            'url',
            'languageId',
            'currencyId',
            'snippetSetId',
        ];

        foreach ($expectedUpdatedFields as $field) {
            static::assertContains($field, $actualUpdatedFields);
        }
    }

    /**
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    private function insertBlog(string $id, EntityRepository $blogRepository): EntityWrittenContainerEvent
    {
        return $blogRepository->upsert([
            [
                'id' => $id,
                'name' => 'testBlog',
                'blogNumber' => 'SWC-1000',
                'stock' => 100,
                'manufacturer' => [
                    'name' => 'app creator',
                ],
                'price' => [
                    [
                        'gross' => 100,
                        'net' => 200,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
                'tax' => [
                    'name' => 'luxury',
                    'taxRate' => '25',
                ],
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @param EntityRepository<ChannelDomainCollection> $channelDomainRepository
     */
    private function insertChannelDomain(
        string $id,
        EntityRepository $channelDomainRepository
    ): EntityWrittenContainerEvent {
        return $channelDomainRepository->upsert([
            [
                'id' => $id,
                'channelId' => TestDefaults::CHANNEL,
                'url' => 'http://test.com',
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            ],
        ], Context::createDefaultContext());
    }
}
