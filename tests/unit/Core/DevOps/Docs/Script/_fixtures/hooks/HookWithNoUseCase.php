<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Script\Execution\Hook;

/**
 * Hook with no use-case tag.
 *
 * @since 6.4.0.0
 */
class HookWithNoUseCase extends Hook
{
    final public const HOOK_NAME = 'no-use-case-hook';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public static function getServiceIds(): array
    {
        return [];
    }
}
