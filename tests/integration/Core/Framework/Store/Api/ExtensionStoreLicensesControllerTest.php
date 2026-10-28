<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Store\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Api\ExtensionStoreLicensesController;
use Contena\Core\Framework\Store\Services\ExtensionStoreLicensesService;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class ExtensionStoreLicensesControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCancelSubscription(): void
    {
        $provider = static::createStub(ExtensionStoreLicensesService::class);
        $provider->method('cancelSubscription');

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $response = $controller->cancelSubscription(1, Context::createDefaultContext());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testRateLicensedExtension(): void
    {
        $provider = static::createStub(ExtensionStoreLicensesService::class);

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $request = new Request();
        $request->request->replace([
            'authorName' => 'Max',
            'headline' => 'Max',
            'rating' => 1,
            'version' => '2.0.0',
        ]);

        $response = $controller->rateLicensedExtension(1, $request, Context::createDefaultContext());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
