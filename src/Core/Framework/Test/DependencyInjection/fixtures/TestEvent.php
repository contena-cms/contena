<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DependencyInjection\fixtures;

use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class TestEvent extends Event implements FlowEventAware
{
    final public const EVENT_NAME = 'test.event';


    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function getContext(): Context
    {
        return $this->context;
    }


    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('user', new EntityType(UserDefinition::class))
            ->add('order', new EntityType(OrderDefinition::class))
        ;
    }
}
