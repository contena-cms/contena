<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;

/**
 * Tests do not belong in the shipped `src/` tree; new ones go to `tests/unit` or
 * `tests/integration`.
 *
 * @internal
 */
class LegacyTestsInSrc
{
    public function __invoke(Context $context): void
    {
        $addedFiles = $context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED);

        $addedLegacyTests = [];

        foreach ($addedFiles->matches('src/**/*Test.php') as $file) {
            $content = $file->getContent();

            if (str_contains($content, 'extends TestCase')) {
                $addedLegacyTests[] = $file->name;
            }
        }

        if (\count($addedLegacyTests) > 0) {
            $context->failure(
                'Don\'t add new testcases in the `/src` folder, for new tests write "real" unit tests under `tests/unit` and if needed a few meaningful integration tests under `tests/integration`:<br/>'
                . implode('<br>', $addedLegacyTests)
            );
        }
    }
}
