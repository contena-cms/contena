<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Files;

use Contena\Core\Framework\Log\Package;

/**
 * Administration snippets stored in the private filesystem (`contena.filesystem.private`).
 *
 * Every `<DIRECTORY>/<source>/<language>.json` or `<locale>.json` (e.g. `snippets/administration/MyIntegration/de.json`)
 * is loaded as the lowest-priority snippet layer, so snippet files shipped by the core, plugins or apps
 * always win. Contena writes the subdirectories named after a theme's technical name itself; use a
 * different source name for your own files and invalidate the `CACHE_TAG` after writing.
 *
 * @codeCoverageIgnore
 */
final class FilesystemAdministrationSnippets
{
    public const DIRECTORY = 'snippets/administration';

    public const CACHE_TAG = 'admin-snippet';

    public static function directoryFor(string $source): string
    {
        return self::DIRECTORY . '/' . $source;
    }
}
