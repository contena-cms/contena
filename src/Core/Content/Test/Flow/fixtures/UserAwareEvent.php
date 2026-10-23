<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\Flow\fixtures;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\UserAware;

/**
 * @internal
 */
class UserAwareEvent implements UserAware, FlowEventAware
{
    public function __construct(
        protected string $userId,
        protected ?Context $context = null
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return 'user.aware.event';
    }

    public function getContext(): Context
    {
        return $this->context ?? Context::createDefaultContext();
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
