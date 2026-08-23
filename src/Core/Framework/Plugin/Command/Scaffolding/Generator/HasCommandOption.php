<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

/**
 * @internal
 */
trait HasCommandOption
{
    public function hasCommandOption(): bool
    {
        return true;
    }

    public function getCommandOptionName(): string
    {
        return self::OPTION_NAME;
    }

    public function getCommandOptionDescription(): string
    {
        return self::OPTION_DESCRIPTION;
    }
}
