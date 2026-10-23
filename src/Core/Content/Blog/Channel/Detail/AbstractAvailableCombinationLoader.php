<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Detail;

use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractAvailableCombinationLoader
{
    abstract public function getDecorated(): AbstractAvailableCombinationLoader;

    abstract public function loadCombinations(string $blogId, ChannelContext $channelContext): AvailableCombinationResult;
}
