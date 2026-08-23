<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigEntity;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Twig\NavigationInfo;
use Contena\Frontend\Framework\Twig\TemplateDataExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class TemplateDataExtensionTenantTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTemplateGlobalsUseTheChannelTenantForDirectSqlReads(): void
    {
        $landingPageId = Uuid::randomHex();
        $tenantA = $this->createTenantScope('A', 3, $landingPageId);
        $tenantB = $this->createTenantScope('B', 7, $landingPageId);

        $tenantAGlobals = $this->globals($this->channelContext($tenantA['channel']), [
            'landingPageId' => $landingPageId,
        ]);
        $this->assertScopeGlobals($tenantAGlobals, 3, $tenantA['link'], [$tenantA['parent']]);

        $tenantBGlobals = $this->globals($this->channelContext($tenantB['channel']), [
            'landingPageId' => $landingPageId,
        ]);
        $this->assertScopeGlobals($tenantBGlobals, 7, $tenantB['link'], [$tenantB['parent']]);

        $crossTenantGlobals = $this->globals($this->channelContext($tenantA['channel']), [
            'navigationId' => $tenantB['link'],
        ]);
        $navigation = $this->navigation($crossTenantGlobals);
        static::assertSame($tenantB['link'], $navigation->id);
        static::assertSame([], $navigation->pathIdList);

        $platformConfig = $this->platformSearchConfig();
        $this->repository('blog_search_config')->update([[
            'id' => $platformConfig->getId(),
            'minSearchLength' => 11,
        ]], Context::createDefaultContext());

        $platformGlobals = $this->globals($this->channelContext(TestDefaults::CHANNEL));
        static::assertSame(11, $this->contenaGlobals($platformGlobals)['minSearchLength']);
    }

    /**
     * @return array{channel: string, parent: string, link: string}
     */
    private function createTenantScope(string $suffix, int $minSearchLength, string $landingPageId): array
    {
        $context = Context::createTenantContext($this->createTenant('Template data tenant ' . $suffix)->id);
        $defaultChannel = $this->repository('channel')
            ->search(new Criteria([TestDefaults::CHANNEL]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(ChannelEntity::class, $defaultChannel);

        $memberGroupId = Uuid::randomHex();
        $rootId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $linkId = Uuid::randomHex();
        $channelId = Uuid::randomHex();

        $this->repository('member_group')->create([[
            'id' => $memberGroupId,
            'name' => 'Template data member group ' . $suffix,
        ]], $context);
        $this->repository('category')->create([[
            'id' => $rootId,
            'name' => 'Template data root ' . $suffix,
        ], [
            'id' => $parentId,
            'parentId' => $rootId,
            'parentVersionId' => Defaults::LIVE_VERSION,
            'name' => 'Template data parent ' . $suffix,
        ], [
            'id' => $linkId,
            'parentId' => $parentId,
            'parentVersionId' => Defaults::LIVE_VERSION,
            'type' => CategoryDefinition::TYPE_LINK,
            'name' => 'Template data link ' . $suffix,
            'linkType' => CategoryDefinition::LINK_TYPE_LANDING_PAGE,
            'internalLink' => $landingPageId,
        ]], $context);
        $this->repository('channel')->create([[
            'id' => $channelId,
            'name' => 'Template data channel ' . $suffix,
            'accessKey' => 'template-data-' . strtolower($suffix) . '-' . \bin2hex(\random_bytes(4)),
            'typeId' => $defaultChannel->getTypeId(),
            'languageId' => $defaultChannel->getLanguageId(),
            'countryId' => $defaultChannel->getCountryId(),
            'memberGroupId' => $memberGroupId,
            'navigationCategoryId' => $rootId,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'languages' => [['id' => $defaultChannel->getLanguageId()]],
            'countries' => [['id' => $defaultChannel->getCountryId()]],
        ]], $context);
        $this->repository('blog_search_config')->create([[
            'id' => Uuid::randomHex(),
            'languageId' => $defaultChannel->getLanguageId(),
            'minSearchLength' => $minSearchLength,
        ]], $context);

        return ['channel' => $channelId, 'parent' => $parentId, 'link' => $linkId];
    }

    private function platformSearchConfig(): BlogSearchConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('languageId', Defaults::LANGUAGE_SYSTEM));
        $config = $this->repository('blog_search_config')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(BlogSearchConfigEntity::class, $config);

        return $config;
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return array<string, mixed>
     */
    private function globals(ChannelContext $context, array $attributes = []): array
    {
        $request = new Request([], [], $attributes);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $requestStack = static::getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        try {
            return static::getContainer()->get(TemplateDataExtension::class)->getGlobals();
        } finally {
            $requestStack->pop();
        }
    }

    /**
     * @param array<string, mixed> $globals
     * @param list<string> $expectedPath
     */
    private function assertScopeGlobals(array $globals, int $minSearchLength, string $navigationId, array $expectedPath): void
    {
        static::assertSame($minSearchLength, $this->contenaGlobals($globals)['minSearchLength']);
        $navigation = $this->navigation($globals);
        static::assertSame($navigationId, $navigation->id);
        static::assertSame($expectedPath, $navigation->pathIdList);
    }

    /**
     * @param array<string, mixed> $globals
     *
     * @return array<string, mixed>
     */
    private function contenaGlobals(array $globals): array
    {
        $contena = $globals['contena'] ?? null;
        static::assertIsArray($contena);

        return $contena;
    }

    /**
     * @param array<string, mixed> $globals
     */
    private function navigation(array $globals): NavigationInfo
    {
        $navigation = $this->contenaGlobals($globals)['navigation'] ?? null;
        static::assertInstanceOf(NavigationInfo::class, $navigation);

        return $navigation;
    }

    private function channelContext(string $channelId): ChannelContext
    {
        return static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), $channelId);
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entityName): EntityRepository
    {
        $repository = static::getContainer()->get($entityName . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
