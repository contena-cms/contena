<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

class DefaultChannelTypeCannotBeDeleted extends ContenaHttpException
{
    public function __construct(string $id)
    {
        parent::__construct('Cannot delete system default channel type', ['id' => $id]);
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__CHANNEL_DEFAULT_TYPE_CANNOT_BE_DELETED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
