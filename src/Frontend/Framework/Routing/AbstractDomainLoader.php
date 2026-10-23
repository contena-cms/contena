<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Framework\Routing\Struct\DomainCollection;

/**
 * @phpstan-type Domain = array{url: string, id: string, channelId: string, typeId: string, snippetSetId: string, currencyId: string, languageId: string, themeId: string, maintenance: string, maintenanceIpAllowlist: string, locale: string, themeName: string, parentThemeName: string}
 */
abstract class AbstractDomainLoader
{
    abstract public function getDecorated(): AbstractDomainLoader;

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use loadDomains() instead
     *
     * @return array<string, Domain>
     */
    abstract public function load(): array;

    #[BecomesAbstract(version: 'v6.8.0', description: 'The default implementation that builds the collection from the deprecated load() will be removed.')]
    public function loadDomains(): DomainCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf('Relying on the default implementation of %s::loadDomains() is deprecated. Implement loadDomains() in %s.', self::class, static::class)
        );

        return DomainCollection::fromArray($this->load());
    }
}
