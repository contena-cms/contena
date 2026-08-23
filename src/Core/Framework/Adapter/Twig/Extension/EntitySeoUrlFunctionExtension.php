<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
class EntitySeoUrlFunctionExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityRouteResolver $entityRouteResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('entitySeoUrl', $this->entitySeoUrl(...), [
                'needs_context' => true,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function entitySeoUrl(array $context, string $name, string $primaryKey): string
    {
        return $this->entityRouteResolver->generateSeoUrlPlaceholder(
            $name,
            $primaryKey,
            TwigContextHelper::getChannelContext($context)?->getChannel()->getTypeId(),
        );
    }
}
