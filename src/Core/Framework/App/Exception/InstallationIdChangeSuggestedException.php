<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Exception;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\InstallationId\FingerprintComparisonResult;
use Contena\Core\Framework\App\InstallationId\InstallationId;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class InstallationIdChangeSuggestedException extends AppException
{
    public function __construct(
        public readonly InstallationId $installationId,
        public readonly FingerprintComparisonResult $comparisonResult,
    ) {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            AppException::INSTALLATION_ID_CHANGE_SUGGESTED,
            'Changes in your system were detected that suggest a change of the installation ID.'
        );
    }
}
