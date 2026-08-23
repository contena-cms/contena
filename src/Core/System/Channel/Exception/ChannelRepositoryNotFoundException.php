<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class ChannelRepositoryNotFoundException extends ContenaHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct(
            'ChannelRepository for entity "{{ entityName }}" does not exist.',
            ['entityName' => $entity]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__CHANNEL_REPOSITORY_NOT_FOUND';
    }
}
