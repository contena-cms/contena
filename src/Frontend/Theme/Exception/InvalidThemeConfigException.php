<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidThemeConfigException extends ContenaHttpException
{
    public function __construct(string $fieldName)
    {
        parent::__construct('Unable to find setter for config field "{{ fieldName }}"', ['fieldName' => $fieldName]);
    }

    public function getErrorCode(): string
    {
        return 'THEME__INVALID_THEME_CONFIG';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
