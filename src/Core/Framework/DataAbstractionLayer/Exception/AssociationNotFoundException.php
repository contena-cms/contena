<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use {DomainException}::associationNotFound() instead
 */
class AssociationNotFoundException extends ContenaHttpException
{
    public function __construct(string $field)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'Can not find association by name {{ association }}',
            ['association' => $field]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__ASSOCIATION_NOT_FOUND';
    }
}
