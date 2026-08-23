<?php declare(strict_types=1);

namespace Contena\Core\Framework\SystemCheck\Check;

/**
 * @codeCoverageIgnore
 */
enum Status implements \JsonSerializable
{
    case OK;
    case UNKNOWN;
    case SKIPPED;
    case WARNING;
    case ERROR;
    case FAILURE;

    public function jsonSerialize(): string
    {
        return $this->name;
    }
}
