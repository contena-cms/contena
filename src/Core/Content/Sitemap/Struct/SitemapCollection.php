<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Sitemap>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SitemapCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Sitemap::class;
    }
}
