<?php declare(strict_types=1);

namespace Contena\Core\DevOps\Docs\Script;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
interface ScriptReferenceGenerator
{
    /**
     * @return array<string, string>
     */
    public function generate(): array;
}
