<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo;

use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;

class SeoUrlRouteNameEnumProvider implements FieldEnumProviderInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry)
    {
    }

    public function isSupported(string $entity, string $fieldName): bool
    {
        return $entity === SeoUrlDefinition::ENTITY_NAME && $fieldName === 'routeName';
    }

    public function getChoices(): array
    {
        $values = [];

        foreach ($this->seoUrlRouteRegistry->getSeoUrlRoutes() as $routeName => $_route) {
            $values[] = (string) $routeName;
        }

        return array_values(array_unique($values));
    }
}
