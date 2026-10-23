<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class PluginCannotBeDeletedException extends ContenaHttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            'Cannot delete plugin. Error: {{ error }}',
            ['error' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_CANNOT_BE_DELETED';
    }
}
