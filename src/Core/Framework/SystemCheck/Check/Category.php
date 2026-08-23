<?php declare(strict_types=1);

namespace Contena\Core\Framework\SystemCheck\Check;

/**
 * @codeCoverageIgnore
 */
enum Category: int
{
    case SYSTEM = 0;

    case FEATURE = 8;

    case EXTERNAL = 32;

    case AUXILIARY = 128;
}
