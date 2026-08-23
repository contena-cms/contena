<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Exception;

use Contena\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class PluginComposerJsonInvalidException extends PluginException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        string $composerJsonPath,
        array $errors
    ) {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__PLUGIN_COMPOSER_JSON_INVALID',
            'The file "{{ composerJsonPath }}" is invalid. Errors:' . \PHP_EOL . '{{ errorsString }}',
            ['composerJsonPath' => $composerJsonPath, 'errorsString' => implode(\PHP_EOL, $errors), 'errors' => $errors]
        );
    }
}
