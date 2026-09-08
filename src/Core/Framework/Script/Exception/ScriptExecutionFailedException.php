<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Script\ScriptException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ScriptExecutionFailedException extends ScriptException
{
    public const ERROR_CODE = 'FRAMEWORK_SCRIPT_EXECUTION_FAILED';

    public function __construct(
        string $hook,
        string $scriptName,
        \Throwable $previous
    ) {
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $errorCode = self::ERROR_CODE;

        $rootException = $previous->getPrevious();
        if ($rootException instanceof HttpExceptionInterface) {
            $statusCode = $rootException->getStatusCode();
        }

        if ($rootException instanceof ContenaHttpException) {
            $errorCode = $rootException->getErrorCode();
        }

        parent::__construct(
            $statusCode,
            $errorCode,
            \sprintf(
                'Execution of script "%s" for Hook "%s" failed with message: %s',
                $scriptName,
                $hook,
                $previous->getMessage()
            ),
            [],
            $previous
        );
    }
}
