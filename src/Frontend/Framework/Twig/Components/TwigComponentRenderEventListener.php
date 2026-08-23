<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig\Components;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;

/**
 * @internal
 */
#[AsEventListener]
final class TwigComponentRenderEventListener
{
    public function __construct(private readonly string $environment)
    {
    }

    public function __invoke(PreRenderEvent $event): void
    {
        $mountedComponent = $event->getMountedComponent();
        $variables = $event->getVariables();
        $metadata = $event->getMetadata();
        $attributesVariable = $metadata->getAttributesVar();
        $attributes = $variables[$attributesVariable] ?? null;

        if (!$attributes instanceof ComponentAttributes) {
            return;
        }

        $additionalAttributes = [
            'data-component-name' => $metadata->getName(),
        ];

        if ($this->environment === 'dev') {
            $additionalAttributes['data-component-template'] = $metadata->getTemplate();

            if ($mountedComponent->hasExtraMetadata('hostTemplate')) {
                $hostTemplate = $mountedComponent->getExtraMetadata('hostTemplate');
                $additionalAttributes['data-component-parent'] = $this->pathToComponentName($hostTemplate);
                $additionalAttributes['data-component-parent-template'] = $hostTemplate;
            }
        }

        $variables[$attributesVariable] = $attributes->defaults($additionalAttributes);
        $event->setVariables($variables);
    }

    private function pathToComponentName(string $path): string
    {
        $path = str_starts_with($path, 'components/') ? substr($path, \strlen('components/')) : $path;
        $path = str_ends_with($path, '.html.twig') ? substr($path, 0, -\strlen('.html.twig')) : $path;

        return str_replace('/', ':', $path);
    }
}
