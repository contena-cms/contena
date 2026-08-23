<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\System\Channel\Channel\ChannelApiInfoController;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class OpenApi3Test extends TestCase
{
    use KernelTestBehaviour;

    public function testRequestOpenApi3Json(): void
    {
        $response = self::getContainer()->get(ChannelApiInfoController::class)->info(new Request());

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));

        $content = $response->getContent();
        static::assertIsString($content);
        $schema = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('3.2.0', $schema['openapi']);
        static::assertArrayHasKey('/context', $schema['paths']);
        static::assertArrayHasKey('/_info/openapi3.json', $schema['paths']);
        static::assertArrayHasKey('/_info/open-api-schema.json', $schema['paths']);
        static::assertArrayHasKey('/_info/stoplightio.html', $schema['paths']);
        static::assertArrayHasKey('/_info/routes', $schema['paths']);
    }
}
