<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Template;

use Contena\Core\Framework\App\Manifest\Manifest;

/**
 * @internal only for use by the app-system
 */
abstract class AbstractTemplateLoader
{
    /**
     * Returns the list of template paths the given app ships
     *
     * @return array<string>
     */
    abstract public function getTemplatePathsForApp(Manifest $app): array;

    /**
     * Returns the content of the template
     */
    abstract public function getTemplateContent(string $path, Manifest $app): string;
}
