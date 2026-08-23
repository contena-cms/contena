<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context;

use Contena\Core\Framework\Context;

class AdminChannelApiSource extends ChannelApiSource
{
    public string $type = 'admin-channel-api';

    public function __construct(
        string $channelId,
        protected Context $originalContext,
    ) {
        parent::__construct($channelId);
    }

    public function getOriginalContext(): Context
    {
        return $this->originalContext;
    }
}
