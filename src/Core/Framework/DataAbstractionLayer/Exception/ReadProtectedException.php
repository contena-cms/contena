<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class ReadProtectedException extends ContenaHttpException
{
    public function __construct(
        string $field,
        string $scope
    ) {
        parent::__construct(
            'The field/association "{{ field }}" is read protected for your scope "{{ scope }}"',
            [
                'field' => $field,
                'scope' => $scope,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__READ_PROTECTED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
