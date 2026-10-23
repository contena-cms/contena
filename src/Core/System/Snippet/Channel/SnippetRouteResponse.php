<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Channel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Snippet\Channel\SnippetRouteTest
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 *
 * @extends StoreApiResponse<SnippetSetResultList>
 */
class SnippetRouteResponse extends StoreApiResponse
{
    public function getResult(): SnippetSetResultList
    {
        return $this->object;
    }
}
