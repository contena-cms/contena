<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\Channel\Channel\StoreApiInfoController;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class OpenApi3Test extends TestCase
{
    use KernelTestBehaviour;

    public function testRequestOpenApi3Json(): void
    {
        $response = self::getContainer()->get(StoreApiInfoController::class)->info(new Request());

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));
    }
}
