<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Snippet\SalesChannel\SnippetRouteTest
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 *
 * @extends StoreApiResponse<SnippetSetResultList>
 */
#[Package('discovery')]
class SnippetRouteResponse extends StoreApiResponse
{
    public function getResult(): SnippetSetResultList
    {
        return $this->object;
    }
}
