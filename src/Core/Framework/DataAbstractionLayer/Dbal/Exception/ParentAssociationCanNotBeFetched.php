<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Symfony\Component\HttpFoundation\Response;

class ParentAssociationCanNotBeFetched extends DataAbstractionLayerException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__PARENT_ASSOCIATION_CAN_NOT_BE_FETCHED',
            'It is not possible to read the parent association directly. Please read the parents via a separate call over the repository'
        );
    }
}
