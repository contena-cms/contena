<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use \Contena\Core\Framework\Plugin\PluginException::kernelPluginLoaderError instead
 */
class KernelPluginLoaderException extends ContenaHttpException
{
    public function __construct(
        string $plugin,
        string $reason
    ) {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'Failed to load plugin "{{ plugin }}". Reason: {{ reason }}',
            ['plugin' => $plugin, 'reason' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__KERNEL_PLUGIN_LOADER_ERROR';
    }
}
