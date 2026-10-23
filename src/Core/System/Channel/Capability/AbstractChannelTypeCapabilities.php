<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Capability;


/**
 * Register one implementation per sales channel type id with the tag `contena.channel.type_capabilities`.
 * Types without an implementation get the defaults of this class.
 *
 * @internal
 */
abstract class AbstractChannelTypeCapabilities
{
    abstract public function getChannelTypeId(): string;

    public function isTransactional(): bool
    {
        return false;
    }
}
