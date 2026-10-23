<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class LiveVersionDeleteException extends ContenaHttpException
{
    public function __construct()
    {
        parent::__construct('Live version can not be deleted. Delete entity instead.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__LIVE_VERSION_DELETE';
    }
}
