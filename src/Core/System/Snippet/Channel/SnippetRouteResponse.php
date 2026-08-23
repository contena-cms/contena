<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Snippet\Channel\SnippetRouteTest
 *
 * @extends ChannelApiResponse<SnippetSetResultList>
 */
class SnippetRouteResponse extends ChannelApiResponse
{
    public function getResult(): SnippetSetResultList
    {
        return $this->object;
    }
}
