<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

use Contena\Core\DevOps\Docs\Script\ServiceReferenceGenerator;

/**
 * @script-service data_loading
 */
class ServiceWithContenaReturnType
{
    /**
     * @return ServiceReferenceGenerator
     */
    public function foo(): void
    {
    }
}
