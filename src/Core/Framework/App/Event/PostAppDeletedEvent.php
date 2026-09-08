<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @final
 *
 * @codeCoverageIgnore
 */
class PostAppDeletedEvent extends Event implements ContenaEvent
{
    final public const NAME = 'app.deleted.post';

    public function __construct(
        public readonly string $appName,
        public readonly string $sourceType,
        private readonly Context $context,
        public readonly bool $keepUserData = false
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
