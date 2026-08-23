<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\BlogListResponse;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Seo\Channel\ChannelApiSeoResolver;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Test\TestBlogSeoUrlRoute;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelApiSeoResolver::class)]
class ChannelApiSeoResolverTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $subscribedEvents = ChannelApiSeoResolver::getSubscribedEvents();

        static::assertCount(1, $subscribedEvents);
        static::assertArrayHasKey(KernelEvents::RESPONSE, $subscribedEvents);
        static::assertSame('addSeoInformation', $subscribedEvents[KernelEvents::RESPONSE][0]);
        static::assertSame(11000, $subscribedEvents[KernelEvents::RESPONSE][1]);
    }

    public function testAddSeoInformation(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            static::createStub(ChannelContext::class),
        );

        $blogEntity = $this->createBlogEntity();
        $response = new BlogListResponse(new EntitySearchResult(
            1,
            new BlogCollection([$blogEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        static::assertEmpty($blogEntity->getSeoUrls());

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($blogEntity->getSeoUrls());
    }

    public function testAddSeoInformationWithExtensions(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            static::createStub(ChannelContext::class),
        );

        $searchResult = new EntitySearchResult(
            0,
            new BlogCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $blog = $this->createBlogEntity();

        $result = new MockSeoUrlAwareExtension();
        $result->addSearchResult($blog);

        $searchResult->addExtension('multiSearchResult', $result);
        $response = new BlogListResponse($searchResult);

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        static::assertEmpty($blog->getSeoUrls());

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($blog->getSeoUrls());
    }

    public function testAddSeoInformationForSearchResultNestedInStructVars(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            static::createStub(ChannelContext::class),
        );

        $blog = $this->createBlogEntity();
        $nestedResult = new EntitySearchResult(
            1,
            new BlogCollection([$blog]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $searchResult = new EntitySearchResult(
            0,
            new BlogCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
        $searchResult->addExtension('cmsSlotData', new MockNestedSearchResultStruct($nestedResult));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new BlogListResponse($searchResult)
        );

        static::assertEmpty($blog->getSeoUrls());

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($blog->getSeoUrls());
    }

    #[DoesNotPerformAssertions]
    public function testResponseIsNotChannelApiResponse(): void
    {
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);
    }

    public function testRequestHeaderDoesNotIncludeSeoUrls(): void
    {
        $blogEntity = $this->createBlogEntity();
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, static::createStub(ChannelContext::class));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new BlogListResponse(new EntitySearchResult(
                1,
                new BlogCollection([$blogEntity]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )),
        );

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNull($blogEntity->getSeoUrls());
    }

    public function testContextIsNoChannelContext(): void
    {
        $blogEntity = $this->createBlogEntity();

        $response = new BlogListResponse(new EntitySearchResult(
            1,
            new BlogCollection([$blogEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            Context::createDefaultContext(),
        );

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNull($blogEntity->getSeoUrls());
    }

    private function createBlogEntity(string $identifier = 'random'): ChannelBlogEntity
    {
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setUniqueIdentifier($identifier);

        return $blogEntity;
    }

    /**
     * @param array<string> $foreignKeys
     */
    private function createChannelApiSeoResolver(array $foreignKeys = ['random']): ChannelApiSeoResolver
    {
        $definitionInstanceRegistry = $this->getDefinitionRegistry();

        $seoUrlCollection = new SeoUrlCollection();

        foreach ($foreignKeys as $foreignKey) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setUniqueIdentifier('seo-url.' . $foreignKey);
            $seoUrlEntity->setForeignKey($foreignKey);

            $seoUrlCollection->add($seoUrlEntity);
        }

        $entitySearchResult = new EntitySearchResult(
            1,
            $seoUrlCollection,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $blogDefinition = $definitionInstanceRegistry->getByClassOrEntityName('blog');

        // not a PHPUnit assertion to avoid indirect assertions and hiding risky tests, narrows from EntityDefinition
        \assert($blogDefinition instanceof BlogDefinition);

        $channelRepository = static::createStub(ChannelRepository::class);
        $channelRepository
            ->method('search')
            ->willReturn($entitySearchResult);

        return new ChannelApiSeoResolver(
            $channelRepository,
            $definitionInstanceRegistry,
            static::createStub(ChannelDefinitionInstanceRegistry::class),
            new SeoUrlRouteRegistry([new TestBlogSeoUrlRoute($blogDefinition)]),
        );
    }

    private function getDefinitionRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                BlogDefinition::class,
                ChannelBlogDefinition::class,
                SeoUrlDefinition::class,
                BlogTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}
