<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\EventListener;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\EventListener\ExpectationSubscriber;
use Contena\Core\Framework\Api\Exception\ExpectationFailedException;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Kernel;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(ExpectationSubscriber::class)]
class ExpectationSubscriberTest extends TestCase
{
    private ExpectationSubscriber $expectationSubscriber;

    protected function setUp(): void
    {
        $this->expectationSubscriber = new ExpectationSubscriber('6.3.0.0', []);
        InstalledVersions::reload([
            'root' => [
                'name' => 'contena/production',
                'pretty_version' => '6.3.0.0',
                'version' => '6.3.0.0',
                'reference' => 'foo',
                'type' => 'project',
                'install_path' => __DIR__,
                'aliases' => [],
                'dev' => false,
            ],
            'versions' => [
                'contena/core' => [
                    'version' => '6.3.0.0',
                    'dev_requirement' => false,
                ],
            ],
        ]);
    }

    public function testExpectFailsOutdatedContenaVersion(): void
    {
        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'contena/core:~6.4');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        static::expectException(ExpectationFailedException::class);

        $this->expectationSubscriber->checkExpectations($event);
    }

    #[DoesNotPerformAssertions]
    public function testExpectMatchesContenaVersion(): void
    {
        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'contena/core:~6.3.0.0');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectationSubscriber->checkExpectations($event);
    }

    public function testExpectMatchesContenaVersionButNotPlugin(): void
    {
        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'contena/core:~6.3.0.0,ct/paypal:*');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        static::expectException(ExpectationFailedException::class);

        $this->expectationSubscriber->checkExpectations($event);
    }

    #[DoesNotPerformAssertions]
    public function testExpectMatchesContenaVersionAndPlugin(): void
    {
        $this->expectationSubscriber = new ExpectationSubscriber('6.3.0.0', [['composerName' => 'ct/paypal', 'active' => true, 'version' => '1.0.0']]);

        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'contena/core:~6.3.0.0,ct/paypal:*');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectationSubscriber->checkExpectations($event);
    }

    public function testExpectMatchesContenaVersionAndPluginIsNotActive(): void
    {
        $this->expectationSubscriber = new ExpectationSubscriber('6.3.0.0', [['composerName' => 'ct/paypal', 'active' => false, 'version' => '1.0.0']]);

        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'contena/core:~6.3.0.0,ct/paypal:*');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        static::expectException(ExpectationFailedException::class);

        $this->expectationSubscriber->checkExpectations($event);
    }

    public function testExpectationsAreRejectedOnRoutesWithoutAuthentication(): void
    {
        $request = $this->makeRequest();
        $request->attributes->set('auth_required', false);
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'contena/core:~6.4');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectExceptionObject(ApiException::expectationNotSupported());

        $this->expectationSubscriber->checkExpectations($event);
    }

    public function testRejectionOnRoutesWithoutAuthenticationDoesNotRevealUnavailablePackages(): void
    {
        $request = $this->makeRequest();
        $request->attributes->set('auth_required', false);
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'ct/not-installed:*');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectExceptionObject(ApiException::expectationNotSupported());

        $this->expectationSubscriber->checkExpectations($event);
    }

    #[DoesNotPerformAssertions]
    public function testRoutesWithoutAuthenticationPassWhenNoHeaderIsSent(): void
    {
        $request = $this->makeRequest();
        $request->attributes->set('auth_required', false);

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectationSubscriber->checkExpectations($event);
    }

    #[DoesNotPerformAssertions]
    public function testRoutesWithoutAuthenticationPassWhenHeaderIsEmpty(): void
    {
        $request = $this->makeRequest();
        $request->attributes->set('auth_required', false);
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, '');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectationSubscriber->checkExpectations($event);
    }

    /**
     * Routes bound to `%contena.api.api_browser.auth_required_str%` receive the flag as "0"/"1".
     */
    public function testExpectationsAreRejectedWhenAuthenticationIsDisabledAsString(): void
    {
        $request = $this->makeRequest();
        $request->attributes->set('auth_required', '0');
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'contena/core:~6.4');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectExceptionObject(ApiException::expectationNotSupported());

        $this->expectationSubscriber->checkExpectations($event);
    }

    public function testExpectationsAreCheckedWhenAuthenticationIsEnabledAsString(): void
    {
        $request = $this->makeRequest();
        $request->attributes->set('auth_required', '1');
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'contena/core:~6.4');

        $event = new ControllerEvent(
            static::createStub(Kernel::class),
            $this->setUp(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        static::expectException(ExpectationFailedException::class);

        $this->expectationSubscriber->checkExpectations($event);
    }

    private function makeRequest(): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID]);

        return $request;
    }
}
