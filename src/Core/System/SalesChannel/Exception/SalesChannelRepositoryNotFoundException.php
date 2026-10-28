<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelRepositoryNotFoundException extends ContenaHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct(
            'SalesChannelRepository for entity "{{ entityName }}" does not exist.',
            ['entityName' => $entity]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__SALES_CHANNEL_REPOSITORY_NOT_FOUND';
    }
}
