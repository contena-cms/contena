<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\FindVariant;

use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<FoundCombination>
 */
class FindBlogVariantRouteResponse extends ChannelApiResponse
{
    public function getFoundCombination(): FoundCombination
    {
        return $this->object;
    }
}
