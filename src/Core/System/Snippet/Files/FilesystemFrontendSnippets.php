<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Files;


/**
 * Frontend snippets stored in the private filesystem (`contena.filesystem.private`).
 *
 * Every `<DIRECTORY>/<source>/<name>.<language>.json` or `<name>.<locale>.json` (e.g. `snippets/frontend/MyIntegration/frontend.de.json`)
 * is loaded as the lowest-priority snippet layer, so snippet files shipped by the core, plugins or apps
 * always win. The `<source>` directory is optional: files placed directly below `<DIRECTORY>` are attributed
 * to the author `custom`, with their domain (`frontend` for `frontend.de.json`) as technical name.
 * Clear the cache after writing, the frontend caches its translation catalogues.
 *
 * @codeCoverageIgnore
 */
final class FilesystemFrontendSnippets
{
    public const DIRECTORY = 'snippets/frontend';

    public const ROOT_AUTHOR = 'custom';

    public static function directoryFor(string $source): string
    {
        return self::DIRECTORY . '/' . $source;
    }
}
