<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\Docs\Command\App;

use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\Docs\App\DocsAppEventCommand;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Util\Hasher;

/**
 * @internal
 */
class DocsAppEventCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUptoDateEventDoc(): void
    {
        // Always check if the docs are up-to-date for the current minor branch
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $docsAppEventCommand = static::getContainer()->get(DocsAppEventCommand::class);

        $savedContents = @file_get_contents($docsAppEventCommand->getListEventPath()) ?: '';

        static::assertSame(
            Hasher::hash($savedContents, 'md5'),
            Hasher::hash($docsAppEventCommand->render(), 'md5'),
            'The webhook events app system document is not up to date' . \PHP_EOL
            . 'Run command docs:app-system-events to get new the webhook-events-reference.md file' . \PHP_EOL
            . 'This file also need to be uploaded to gitbook at /resources/references/app-reference/webhook-events-reference.md!'
        );
    }
}
