<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Exception;

use Contena\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class PluginNotFoundException extends PluginException
{
    public function __construct(string $pluginName)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            'FRAMEWORK__PLUGIN_NOT_FOUND',
            'Plugin by name "{{ name }}" not found.',
            ['name' => $pluginName]
        );
    }
}
