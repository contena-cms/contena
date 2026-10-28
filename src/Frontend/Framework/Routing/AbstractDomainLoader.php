<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Routing;

use Contena\Core\Framework\Log\Package;

/**
 * @phpstan-type Domain = array{url: string, id: string, salesChannelId: string, typeId: string, snippetSetId: string, currencyId: string, languageId: string, themeId: string, maintenance: string, maintenanceIpAllowlist: string, locale: string, themeName: string, parentThemeName: string}
 */
#[Package('discovery')]
abstract class AbstractDomainLoader
{

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use loadDomains() instead
     *
     * @return array<string, Domain>
     */
    abstract public function load(): array;

}
