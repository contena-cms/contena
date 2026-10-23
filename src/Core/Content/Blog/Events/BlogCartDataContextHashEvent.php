<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The data context hash of the cart blogs only covers the sales channel context and the tax rules.
 * The cart reloads blog data only when this hash changes, so extensions whose blog data or prices depend on other state get stale cart data.
 * Listeners can add parts to this event to include additional state in the hash for every state outside the sales channel context to force a reload of blog data when that state changes.
 */
class BlogCartDataContextHashEvent extends Event implements ContenaChannelEvent
{
    /**
     * @var array<string, string|int|float|bool|array<mixed>|null>
     */
    private array $parts = [];


    /**
     * @param string|int|float|bool|array<mixed>|null $value
     */
    public function addPart(string $name, string|int|float|bool|array|null $value): void
    {
        $this->parts[$name] = $value;
    }

    /**
     * @return array<string, string|int|float|bool|array<mixed>|null>
     */
    public function getParts(): array
    {
        $parts = $this->parts;
        ksort($parts);

        return $parts;
    }




    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
