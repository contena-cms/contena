<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
class SeoUrlTemplateRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreate(): void
    {
        $id = Uuid::randomHex();
        $template = [
            'id' => $id,
            'channelId' => TestDefaults::CHANNEL,
            'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => BlogPageSeoUrlRoute::DEFAULT_TEMPLATE,
        ];

        $context = Context::createDefaultContext();
        /** @var EntityRepository<SeoUrlTemplateCollection> $repo */
        $repo = static::getContainer()->get('seo_url_template.repository');
        $events = $repo->create([$template], $context);
        static::assertNotNull($events->getEvents());
        static::assertCount(1, $events->getEvents());

        $event = $events->getEventByEntityName(SeoUrlTemplateDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());
    }

    /**
     * @param array<string, string> $template
     */
    #[DataProvider('templateUpdateDataProvider')]
    public function testUpdate(string $id, array $template): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<SeoUrlTemplateCollection> $repo */
        $repo = static::getContainer()->get('seo_url_template.repository');
        $repo->create([$template], $context);

        $update = [
            'id' => $id,
            'routeName' => 'foo_bar',
        ];
        $events = $repo->update([$update], $context);
        $event = $events->getEventByEntityName(SeoUrlTemplateDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());

        $first = $repo->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertNotNull($first);
        static::assertSame($update['id'], $first->getId());
        static::assertSame($update['routeName'], $first->getRouteName());
    }

    public function testDelete(): void
    {
        $id = Uuid::randomHex();
        $template = [
            'id' => $id,
            'channelId' => TestDefaults::CHANNEL,
            'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => BlogPageSeoUrlRoute::DEFAULT_TEMPLATE,
        ];

        $context = Context::createDefaultContext();
        /** @var EntityRepository<SeoUrlTemplateCollection> $repo */
        $repo = static::getContainer()->get('seo_url_template.repository');
        $repo->create([$template], $context);

        $result = $repo->delete([['id' => $id]], $context);
        $event = $result->getEventByEntityName(SeoUrlTemplateDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertSame([$id], $event->getIds());

        $first = $repo->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertNull($first);
    }

    public static function templateUpdateDataProvider(): \Generator
    {
        $templates = [
            [
                'id' => null,
                'channelId' => TestDefaults::CHANNEL,
                'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
                'entityName' => BlogDefinition::ENTITY_NAME,
                'template' => BlogPageSeoUrlRoute::DEFAULT_TEMPLATE,
            ],
            [
                'id' => null,
                'channelId' => TestDefaults::CHANNEL,
                'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
                'entityName' => BlogDefinition::ENTITY_NAME,
                'template' => '',
            ],
        ];

        foreach ($templates as $template) {
            $id = Uuid::randomHex();
            $template['id'] = $id;

            yield [
                'id' => $id,
                'template' => $template,
            ];
        }
    }
}
