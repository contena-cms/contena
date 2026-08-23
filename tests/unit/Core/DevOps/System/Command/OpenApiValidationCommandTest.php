<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\System\Command\OpenApiValidationCommand;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(OpenApiValidationCommand::class)]
class OpenApiValidationCommandTest extends TestCase
{
    public function testRunWithoutErrors(): void
    {
        $command = new OpenApiValidationCommand(
            new MockHttpClient([new MockResponse('{"messages": [], "schemaValidationMessages": []}', [])]),
            static::createStub(DefinitionService::class)
        );
        $tester = new CommandTester($command);

        $tester->execute([]);

        static::assertSame(0, $tester->getStatusCode());
    }

    public function testRunWithErrors(): void
    {
        $command = new OpenApiValidationCommand(
            new MockHttpClient(
                [new MockResponse(json_encode([
                    'schemaValidationMessages' => [
                        [
                            'level' => 'error',
                            'domain' => 'validation',
                            'keyword' => 'oneOf',
                            'message' => 'instance failed to match exactly one schema (matched 0 out of 2)',
                            'schema' => [
                                'loadingURI' => '#',
                                'pointer' => "\/definitions\/Components\/properties\/schemas\/patternProperties\/^[a-zA-Z0-9\\.\\-_]+$",
                            ],
                            'instance' => [
                                'pointer' => "\/components\/schemas\/foo",
                            ],
                        ],
                    ],
                    'messages' => [],
                ], \JSON_THROW_ON_ERROR), [])]
            ),
            static::createStub(DefinitionService::class)
        );
        $tester = new CommandTester($command);

        $tester->execute([]);

        static::assertSame(1, $tester->getStatusCode());
    }
}
