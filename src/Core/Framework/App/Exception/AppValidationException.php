<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Exception;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Validation\Error\Error;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal only for use by the app-system
 */
class AppValidationException extends AppException
{
    /**
     * @param list<Error> $errors
     */
    public function __construct(
        string $appName,
        private readonly array $errors
    ) {
        $message = \sprintf(
            "The app \"%s\" is invalid:\n",
            $appName
        );

        foreach ($errors as $error) {
            $message .= "\n" . $error->getMessage();
        }

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            AppException::VALIDATION_FAILED,
            $message
        );
    }

    /**
     * @return list<Error>
     */
    public function getValidationErrors(): array
    {
        return $this->errors;
    }
}
