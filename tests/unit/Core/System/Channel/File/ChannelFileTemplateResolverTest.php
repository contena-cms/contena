<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Contena\Core\System\Channel\File\ChannelFileTemplateResolver;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Event\ChannelFileTemplateResolveEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(ChannelFileTemplateResolver::class)]
class ChannelFileTemplateResolverTest extends TestCase
{
    public function testItUsesExtensionOwnedBaseTemplateFromResolvedChain(): void
    {
        $resolver = $this->createResolver([
            '@VendorBase/files/agentic/llms.txt.twig' => '{% block content %}vendor base{% endblock %}',
            '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}ucp + {{ parent() }}{% endblock %}',
        ], ['Ucp' => 0, 'VendorBase' => -1, 'Framework' => -2]);

        $file = $this->createChannelFile([
            'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            'VendorBase' => '@VendorBase/files/agentic/llms.txt.twig',
        ]);

        static::assertSame('@VendorBase/files/agentic/llms.txt.twig', $resolver->getBaseTemplateName($file));
        static::assertSame('@Ucp/files/agentic/llms.txt.twig', $resolver->getRenderTemplateName($file));
    }

    public function testItUsesResolvedChainInsteadOfParsingTemplateSource(): void
    {
        $resolver = $this->createResolver([
            '@Framework/files/agentic/llms.txt.twig' => '{# {% extends "ignored.html.twig" %} #}{% set example = "{% sw_extends ignored %}" %}{% block content %}core{% endblock %}',
            '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}ucp + {{ parent() }}{% endblock %}',
        ], ['Ucp' => 0, 'Framework' => -1]);

        $file = $this->createChannelFile([
            'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            'Framework' => '@Framework/files/agentic/llms.txt.twig',
        ]);

        static::assertSame('@Framework/files/agentic/llms.txt.twig', $resolver->getBaseTemplateName($file));
        static::assertSame('@Ucp/files/agentic/llms.txt.twig', $resolver->getRenderTemplateName($file));
    }

    public function testItResolvesTemplateChainForChannelContext(): void
    {
        $hierarchyBuilder = new ChannelFileTemplateResolverTestMutableHierarchyBuilder(['Framework' => 0]);
        $dispatcher = new EventDispatcher();
        $channelId = 'channel-id';
        $dispatcher->addListener(ChannelFileTemplateResolveEvent::class, static function (ChannelFileTemplateResolveEvent $event) use ($hierarchyBuilder, $channelId): void {
            static::assertSame($channelId, $event->channelId);

            $hierarchyBuilder->setHierarchy(['Ucp' => 0, 'Framework' => -1]);
        });

        $resolver = $this->createResolverWithHierarchyBuilder([
            '@Framework/files/agentic/llms.txt.twig' => '{% block content %}core{% endblock %}',
            '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block content %}ucp + {{ parent() }}{% endblock %}',
        ], $hierarchyBuilder, $dispatcher);

        $file = $this->createChannelFile([]);

        static::assertSame('@Framework/files/agentic/llms.txt.twig', $resolver->getBaseTemplateName($file));
        static::assertSame('@Ucp/files/agentic/llms.txt.twig', $resolver->getRenderTemplateName($file, $channelId));
    }

    public function testItResolvesCaseVariantsAsOneTemplateChain(): void
    {
        $resolver = $this->createResolver([
            '@Framework/files/agentic/AGENTS.md.twig' => '{% block content %}core{% endblock %}',
            '@Ucp/files/agentic/agents.md.twig' => '{% block content %}ucp{% endblock %}',
        ], ['Ucp' => 0, 'Framework' => -1]);

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

        static::assertSame([
            'Ucp' => '@Ucp/files/agentic/agents.md.twig',
            'Framework' => '@Framework/files/agentic/AGENTS.md.twig',
        ], $resolver->resolveTemplateChain($file));
    }

    /**
     * @param array<string, string> $templates
     * @param array<string, int> $hierarchy
     */
    private function createResolver(array $templates, array $hierarchy): ChannelFileTemplateResolver
    {
        return $this->createResolverWithHierarchyBuilder(
            $templates,
            new ChannelFileTemplateResolverTestHierarchyBuilder($hierarchy),
            new EventDispatcher(),
        );
    }

    /**
     * @param array<string, string> $templates
     */
    private function createResolverWithHierarchyBuilder(
        array $templates,
        TemplateNamespaceHierarchyBuilderInterface $hierarchyBuilder,
        ?EventDispatcherInterface $eventDispatcher = null
    ): ChannelFileTemplateResolver {
        $loader = new ArrayLoader($templates);
        $twig = new Environment($loader);
        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        return new ChannelFileTemplateResolver(
            new TemplateFinder(
                $twig,
                $loader,
                '',
                new NamespaceHierarchyBuilder([
                    $hierarchyBuilder,
                ]),
                $scopeDetector,
            ),
            new NamespaceHierarchyBuilder([
                $hierarchyBuilder,
            ]),
            $loader,
            $eventDispatcher ?? new EventDispatcher(),
        );
    }

    /**
     * @param array<string, string> $templates
     */
    private function createChannelFile(array $templates): ChannelFile
    {
        return new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            $templates,
        );
    }
}

/**
 * @internal
 */
final readonly class ChannelFileTemplateResolverTestHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
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
final class ChannelFileTemplateResolverTestMutableHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
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
