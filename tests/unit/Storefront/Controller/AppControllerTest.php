<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Api\AppJWTGenerateRoute;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Storefront\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppController::class)]
class AppControllerTest extends TestCase
{
    public function testGenerate(): void
    {
        $appJWTGenerateRoute = $this->createMock(AppJWTGenerateRoute::class);
        $appJWTGenerateRoute->expects($this->once())->method('generate')->with('test');

        $controller = new AppController($appJWTGenerateRoute);
        $controller->generateToken('test', Generator::generateSalesChannelContext());
    }

    public function testGenerateFails(): void
    {
        $appJWTGenerateRoute = $this->createMock(AppJWTGenerateRoute::class);
        $appJWTGenerateRoute->expects($this->once())->method('generate')->willThrowException(AppException::jwtGenerationRequiresCustomerLoggedIn());

        $controller = new AppController($appJWTGenerateRoute);
        $response = $controller->generateToken('test', Generator::generateSalesChannelContext());
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('message', $data);
        static::assertSame('JWT generation requires customer to be logged in', $data['message']);
    }
}
