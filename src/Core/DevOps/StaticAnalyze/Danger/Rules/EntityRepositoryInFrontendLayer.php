<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;

/**
 * The Frontend layer (Controller, Page, Pagelet) must consume Channel API routes
 * instead of querying repositories directly.
 *
 * @internal
 */
class EntityRepositoryInFrontendLayer
{
    public function __invoke(Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        // match added lines in the patch instead of matchesContent(): the diff ships with the file
        // listing (no per-file API download), and only newly introduced usage is flagged;
        // lines mentioning a deprecation are excluded, like the previous filter intended
        $isNewRepoUse = static fn (File $file): bool => preg_match('/^\+(?!.*@deprecated).*EntityRepository/m', $file->patch) === 1;

        $newRepoUseInFrontend = array_merge(
            $files->filterStatus(File::STATUS_MODIFIED)->matches('src/Frontend/Controller/*')->filter($isNewRepoUse)->getElements(),
            $files->filterStatus(File::STATUS_MODIFIED)->matches('src/Frontend/Page/*')->filter($isNewRepoUse)->getElements(),
            $files->filterStatus(File::STATUS_MODIFIED)->matches('src/Frontend/Pagelet/*')->filter($isNewRepoUse)->getElements(),
        );

        if (\count($newRepoUseInFrontend) === 0) {
            return;
        }

        $errorFiles = [];
        foreach ($newRepoUseInFrontend as $file) {
            $errorFiles[] = $file->name . '<br/>';
        }

        $context->failure(
            'Do not use direct repository calls in the Frontend Layer (Controller, Page, Pagelet).'
            . ' Use Channel API routes instead.<br/>'
            . implode('<br>', $errorFiles)
        );
    }
}
