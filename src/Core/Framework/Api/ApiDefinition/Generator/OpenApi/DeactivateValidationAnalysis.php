<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Analysis;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
class DeactivateValidationAnalysis extends Analysis
{
    public function validate(): bool
    {
        return false;
        // deactivate Validitation
    }
}
