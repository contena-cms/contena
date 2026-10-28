<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

#[Package('discovery')]
class UnexpectedFieldConfigValueType extends ContenaHttpException
{
    public function __construct(
        string $fieldConfigName,
        string $expectedType,
        string $givenType
    ) {
        parent::__construct(
            'Expected to load value of "{{ fieldConfigName }}" with type "{{ expectedType }}", but value with type "{{ givenType }}" given.',
            [
                'fieldConfigName' => $fieldConfigName,
                'expectedType' => $expectedType,
                'givenType' => $givenType,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__CMS_UNEXPECTED_VALUE_TYPE';
    }
}
