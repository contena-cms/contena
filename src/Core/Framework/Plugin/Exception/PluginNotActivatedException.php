<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Exception;

use Contena\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class PluginNotActivatedException extends PluginException
{
    public function __construct(string $pluginName)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'FRAMEWORK__PLUGIN_NOT_ACTIVATED',
            'Plugin "{{ plugin }}" is not activated.',
            ['plugin' => $pluginName]
        );
    }
}
