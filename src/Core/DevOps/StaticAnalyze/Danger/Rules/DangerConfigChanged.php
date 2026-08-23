<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;

/**
 * Danger always executes the config, rule classes and runner package of the target branch, so
 * changes to them in a pull request are not applied to that same pull request's Danger run.
 *
 * @internal
 */
class DangerConfigChanged
{
    private const array WATCHED_PATTERNS = [
        '.danger.php',
        'src/Core/DevOps/StaticAnalyze/Danger/*',
        'vendor-bin/danger-php/*',
    ];

    public function __invoke(Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        foreach (self::WATCHED_PATTERNS as $pattern) {
            if ($files->matches($pattern)->count() > 0) {
                $context->notice('Changes to the Danger config, rules or runner (`.danger.php`, `src/Core/DevOps/StaticAnalyze/Danger`, `vendor-bin/danger-php`) are not applied to this pull request\'s own Danger run. They take effect for pull requests opened or updated after the merge.');

                return;
            }
        }
    }
}
