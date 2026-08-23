<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\File\ChannelFileTemplateResolver;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Event\ChannelFileTemplateResolveEvent;
use Contena\Core\System\Channel\File\Rendering\ChannelFileRenderer;
use Contena\Core\System\Channel\File\Rendering\ChannelFileTemplateOverrideLoader;
use Contena\Core\System\Channel\File\Rendering\Extension\ChannelFileRenderParametersExtension;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

/**
 * @internal
 */
#[CoversClass(ChannelFileRenderer::class)]
class ChannelFileRendererTest extends TestCase
{
    public function testOverridesParticipateInContenaTemplateInheritance(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% endblock %}',
                '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new ChannelFileRendererTestHierarchyBuilder(['Ucp' => 0, 'Framework' => -1]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $seoUrlPlaceholderHandler = $this->createSeoUrlPlaceholderHandler();
        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder),
            $templateOverrideLoader,
            $seoUrlPlaceholderHandler,
            $this->createChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            ],
        );

        $context = $this->createChannelContext();

        $content = $renderer->render($file, $context, [
            'Ucp' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}merchant plugin + {{ parent() }}{% endblock %}',
            'Framework' => '{% block content %}merchant core{% endblock %}',
        ]);

        static::assertSame('merchant plugin + merchant core', $content);
        static::assertSame('plugin + core', $renderer->render($file, $context));
    }

    public function testLegacyLowercaseExtensionRendersUppercaseCoreTemplate(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/AGENTS.md.twig' => '{% block content %}core{% endblock %}',
                '@Ucp/files/agentic/agents.md.twig' => '{% sw_extends \'files/agentic/agents.md.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);
        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new ChannelFileRendererTestHierarchyBuilder(['Ucp' => 0, 'Framework' => -1]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);
        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createChannelRepository(),
            $this->createExtensionDispatcher()
        );
        $file = new ChannelFile(
            'agentic',
            'AGENTS.md',
            'files/agentic/AGENTS.md.twig',
            'text/markdown; charset=utf-8',
            'files/agentic/AGENTS.md.twig',
            [],
            [
                'files/agentic/AGENTS.md.twig',
                'files/agentic/agents.md.twig',
            ],
        );

        static::assertSame('plugin + merchant core', $renderer->render($file, $this->createChannelContext(), [
            'Framework' => '{% block content %}merchant core{% endblock %}',
        ]));
    }

    public function testRenderEntryIsResolvedThroughTemplateFinderInsteadOfDiscoveredSourceOrder(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% endblock %}',
                '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $templateFinder = $this->createTemplateFinder($twig, $loader);

        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );

        static::assertSame('plugin + core', $renderer->render($file, $this->createChannelContext()));
    }

    public function testChannelSpecificTemplateHierarchyIsResolvedBeforeRendering(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% endblock %}',
                '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $hierarchyBuilder = new ChannelFileRendererTestMutableHierarchyBuilder(['Framework' => 0]);
        $templateFinder = $this->createTemplateFinder($twig, $loader, $hierarchyBuilder);
        $context = $this->createChannelContext();
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(ChannelFileTemplateResolveEvent::class, static function (ChannelFileTemplateResolveEvent $event) use ($context, $hierarchyBuilder): void {
            static::assertSame($context->getChannelId(), $event->channelId);

            $hierarchyBuilder->setHierarchy(['Ucp' => 0, 'Framework' => -1]);
        });

        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder, $eventDispatcher),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [],
        );

        $content = $renderer->render($file, $context, [
            'Ucp' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}merchant plugin + {{ parent() }}{% endblock %}',
        ]);

        static::assertSame('merchant plugin + core', $content);
    }

    public function testExtensionProvidedBaseTemplateCanBeExtended(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@VendorBase/files/agentic/vendor.txt.twig' => '{% block content %}vendor base{% endblock %}',
                '@Ucp/files/agentic/vendor.txt.twig' => '{% sw_extends \'files/agentic/vendor.txt.twig\' %}{% block content %}ucp + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $hierarchy = ['Ucp' => 0, 'VendorBase' => -1];
        $templateFinder = $this->createTemplateFinder($twig, $loader, $hierarchy);

        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchy),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new ChannelFile(
            'agentic',
            'vendor.txt',
            'files/agentic/vendor.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/vendor.txt.twig',
            [
                'Ucp' => '@Ucp/files/agentic/vendor.txt.twig',
                'VendorBase' => '@VendorBase/files/agentic/vendor.txt.twig',
            ],
        );

        static::assertSame('ucp + vendor base', $renderer->render($file, $this->createChannelContext()));
    }

    public function testUserProvidedContentIsRenderedThroughDedicatedBlock(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% block user_provided_content %}{% endblock %}{% endblock %}',
                '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}plugin + {{ parent() }}{% endblock %}',
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new ChannelFileRendererTestHierarchyBuilder(['Ucp' => 0, 'Framework' => -1]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $seoUrlPlaceholderHandler = $this->createSeoUrlPlaceholderHandler();
        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder),
            $templateOverrideLoader,
            $seoUrlPlaceholderHandler,
            $this->createChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            ],
        );

        $context = $this->createChannelContext();

        $content = $renderer->render($file, $context, [
            'user_provided_content' => '{{ channel.name }} must stay literal.',
        ]);

        static::assertSame('plugin + core{{ channel.name }} must stay literal.', $content);
    }

    public function testSeoUrlPlaceholdersAreReplacedAfterRendering(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $placeholder = SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/search#';
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => 'Search: ' . $placeholder,
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new ChannelFileRendererTestHierarchyBuilder(['Framework' => 0]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $context = $this->createChannelContext();

        $seoUrlPlaceholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlPlaceholderHandler
            ->expects($this->once())
            ->method('replace')
            ->with('Search: ' . $placeholder, '', $context)
            ->willReturn('Search: /search');

        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder),
            $templateOverrideLoader,
            $seoUrlPlaceholderHandler,
            $this->createChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );

        static::assertSame('Search: /search', $renderer->render($file, $context));
    }

    public function testChannelIsReloadedWithLanguagesAndDomainsForTwig(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{{ channel.name }}: {{ channel.languages|length }}/{{ channel.domains|length }}',
            ]),
        ]);
        $twig = new Environment($loader);
        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $hierarchyBuilder = new NamespaceHierarchyBuilder([
            new ChannelFileRendererTestHierarchyBuilder(['Framework' => 0]),
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, $scopeDetector);

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        $contextChannel = $this->createChannel('Context channel');
        $context = $this->createChannelContext($contextChannel);

        $reloadedChannel = $this->createChannel(
            'Reloaded channel',
            new LanguageCollection([$this->createLanguage()]),
            new ChannelDomainCollection([$this->createDomain()]),
        );

        $channelRepository = $this->createChannelRepository(
            $reloadedChannel,
            static function (Criteria $criteria) use ($contextChannel): void {
                static::assertSame([$contextChannel->getId()], $criteria->getIds());

                $associations = $criteria->getAssociations();
                static::assertArrayHasKey('languages', $associations);
                static::assertArrayHasKey('translationCode', $associations['languages']->getAssociations());
                static::assertArrayHasKey('domains', $associations);
            }
        );

        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, $hierarchyBuilder),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $channelRepository,
            $this->createExtensionDispatcher()
        );

        $file = new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );

        static::assertSame('Reloaded channel: 1/1', $renderer->render($file, $context));
    }

    public function testDefaultParametersDoNotCreateFileSpecificContext(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/.well-known/ai-catalog.json.twig' => '{{ channelFileContext|default("none") }}',
            ]),
        ]);
        $twig = new Environment($loader);
        $templateFinder = $this->createTemplateFinder($twig, $loader, ['Framework' => 0]);

        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, ['Framework' => 0]),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createChannelRepository(),
            $this->createExtensionDispatcher()
        );

        $file = new ChannelFile(
            'agentic',
            '.well-known/ai-catalog.json',
            'files/agentic/.well-known/ai-catalog.json.twig',
            'application/json; charset=utf-8',
            'files/agentic/.well-known/ai-catalog.json.twig',
            [
                'Framework' => '@Framework/files/agentic/.well-known/ai-catalog.json.twig',
            ],
        );

        static::assertSame(
            'none',
            $renderer->render($file, $this->createChannelContext())
        );
    }

    public function testRenderParametersCanBeExtendedForSpecificFile(): void
    {
        $templateOverrideLoader = new ChannelFileTemplateOverrideLoader();
        $loader = new ChainLoader([
            $templateOverrideLoader,
            new ArrayLoader([
                '@Framework/files/agentic/llms.txt.twig' => '{{ customAgenticValue }}',
            ]),
        ]);
        $twig = new Environment($loader);
        $templateFinder = $this->createTemplateFinder($twig, $loader, ['Framework' => 0]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ChannelFileRenderParametersExtension::onPost(), static function (ChannelFileRenderParametersExtension $extension): void {
            if ($extension->file->fileName !== 'llms.txt') {
                return;
            }

            \assert(\is_array($extension->result));
            $extension->result['customAgenticValue'] = 'extended';
        });

        $renderer = new ChannelFileRenderer(
            $twig,
            $this->createTemplateResolver($templateFinder, $loader, ['Framework' => 0]),
            $templateOverrideLoader,
            $this->createSeoUrlPlaceholderHandler(),
            $this->createChannelRepository(),
            $this->createExtensionDispatcher($dispatcher)
        );

        $file = new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );

        static::assertSame('extended', $renderer->render($file, $this->createChannelContext()));
    }

    private function createSeoUrlPlaceholderHandler(): SeoUrlPlaceholderHandlerInterface&Stub
    {
        $seoUrlPlaceholderHandler = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlPlaceholderHandler
            ->method('replace')
            ->willReturnArgument(0);

        return $seoUrlPlaceholderHandler;
    }

    /**
     * @param array<string, int> $hierarchy
     */
    private function createTemplateFinder(
        Environment $twig,
        ChainLoader $loader,
        array|TemplateNamespaceHierarchyBuilderInterface $hierarchy = ['Ucp' => 0, 'Framework' => -1]
    ): TemplateFinder {
        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            '',
            $this->createNamespaceHierarchyBuilder($hierarchy),
            $scopeDetector,
        );

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));

        return $templateFinder;
    }

    /**
     * @param array<string, int> $hierarchy
     */
    private function createTemplateResolver(
        TemplateFinder $templateFinder,
        ChainLoader $loader,
        NamespaceHierarchyBuilder|array|TemplateNamespaceHierarchyBuilderInterface $hierarchy = ['Ucp' => 0, 'Framework' => -1],
        ?EventDispatcher $eventDispatcher = null
    ): ChannelFileTemplateResolver {
        return new ChannelFileTemplateResolver(
            $templateFinder,
            $this->createNamespaceHierarchyBuilder($hierarchy),
            $loader,
            $eventDispatcher ?? new EventDispatcher(),
        );
    }

    /**
     * @param array<string, int> $hierarchy
     */
    private function createNamespaceHierarchyBuilder(NamespaceHierarchyBuilder|array|TemplateNamespaceHierarchyBuilderInterface $hierarchy): NamespaceHierarchyBuilder
    {
        if ($hierarchy instanceof NamespaceHierarchyBuilder) {
            return $hierarchy;
        }

        return new NamespaceHierarchyBuilder([
            \is_array($hierarchy) ? new ChannelFileRendererTestHierarchyBuilder($hierarchy) : $hierarchy,
        ]);
    }

    private function createChannelContext(?ChannelEntity $channel = null, ?string $domainId = null): ChannelContext&Stub
    {
        $channel ??= $this->createChannel('Context channel');
        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn($channel->getId());
        $context->method('getChannel')->willReturn($channel);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getDomainId')->willReturn($domainId);

        return $context;
    }

    /**
     * @param \Closure(Criteria, Context): void|null $criteriaAssertion
     *
     * @return EntityRepository<ChannelCollection>&Stub
     */
    private function createChannelRepository(?ChannelEntity $channel = null, ?\Closure $criteriaAssertion = null): EntityRepository&Stub
    {
        $repository = static::createStub(EntityRepository::class);
        $repository
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria, Context $context) use ($channel, $criteriaAssertion): EntitySearchResult {
                if ($criteriaAssertion !== null) {
                    $criteriaAssertion($criteria, $context);
                }

                return new EntitySearchResult(
                    $channel instanceof ChannelEntity ? 1 : 0,
                    new ChannelCollection($channel instanceof ChannelEntity ? [$channel] : []),
                    null,
                    $criteria,
                    $context
                );
            });

        return $repository;
    }

    private function createExtensionDispatcher(?EventDispatcher $eventDispatcher = null): ExtensionDispatcher
    {
        return new ExtensionDispatcher($eventDispatcher ?? new EventDispatcher());
    }

    private function createChannel(
        string $name,
        ?LanguageCollection $languages = null,
        ?ChannelDomainCollection $domains = null,
    ): ChannelEntity {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setName($name);

        if ($languages !== null) {
            $channel->setLanguages($languages);
        }

        if ($domains !== null) {
            $channel->setDomains($domains);
        }

        return $channel;
    }

    private function createLanguage(): LanguageEntity
    {
        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $language->setName('English');

        return $language;
    }

    private function createDomain(): ChannelDomainEntity
    {
        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://example.com');

        return $domain;
    }
}

/**
 * @internal
 */
final readonly class ChannelFileRendererTestHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * @param array<string, int> $hierarchy
     */
    public function __construct(private array $hierarchy)
    {
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        return $this->hierarchy + $namespaceHierarchy;
    }
}

/**
 * @internal
 */
final class ChannelFileRendererTestMutableHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * @param array<string, int> $hierarchy
     */
    public function __construct(private array $hierarchy)
    {
    }

    /**
     * @param array<string, int> $hierarchy
     */
    public function setHierarchy(array $hierarchy): void
    {
        $this->hierarchy = $hierarchy;
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        return $this->hierarchy + $namespaceHierarchy;
    }
}
