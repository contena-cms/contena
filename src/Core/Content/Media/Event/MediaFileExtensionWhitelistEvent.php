<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

class MediaFileExtensionWhitelistEvent extends Event implements ContenaEvent
{
    /**
     * @param array<string> $whitelist
     */
    public function __construct(
        private array $whitelist,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string>
     */
    public function getWhitelist()
    {
        return $this->whitelist;
    }

    /**
     * @param array<string> $whitelist
     */
    public function setWhitelist(array $whitelist): void
    {
        $this->whitelist = $whitelist;
    }
}
