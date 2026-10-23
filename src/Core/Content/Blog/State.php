<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use BlogDefinition::TYPE_DIGITAL and BlogDefinition::TYPE_PHYSICAL instead
 */
final class State
{
    public const IS_PHYSICAL = 'is-physical';
    public const IS_DOWNLOAD = 'is-download';
}
