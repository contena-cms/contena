<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class WellKnownControllerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    public function testRedirectFromPasswordResetRoute(): void
    {
        $response = $this->request('GET', '/.well-known/change-password', []);

        static::assertSame(302, $response->getStatusCode());

        $location = $response->headers->get('Location');

        static::assertIsString($location);
        static::assertStringContainsString('account/profile', $location);
        static::assertStringContainsString('profile-password-form', $location);
    }
}
