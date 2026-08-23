<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class InconsistentCriteriaIdsException extends ContenaHttpException
{
    public function __construct()
    {
        parent::__construct('Inconsistent argument for Criteria. Please filter all invalid values first.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INCONSISTENT_CRITERIA_IDS';
    }
}
