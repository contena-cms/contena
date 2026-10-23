<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Contena\Core\Framework\Script\ScriptException;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ScriptException::class)]
class ScriptExceptionTest extends TestCase
{
    public function testExecutionFailedException(): void
    {
        $exception = ScriptException::scriptExecutionFailed('hook', 'script', new \RuntimeException('test'));

        static::assertInstanceOf(ScriptExecutionFailedException::class, $exception);
        static::assertSame('FRAMEWORK_SCRIPT_EXECUTION_FAILED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testHookMethodOutsideOfChannelContextException(): void
    {
        $exception = ScriptException::hookMethodOutsideOfChannelContext('method');

        static::assertSame('FRAMEWORK__HOOK_METHOD_OUTSIDE_CHANNEL_CONTEXT', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFrontendBundleMissingForHookMethodException(): void
    {
        $exception = ScriptException::frontendBundleMissingForHookMethod('method');

        static::assertSame('FRAMEWORK__HOOK_METHOD_FRONTEND_BUNDLE_MISSING', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testLegacyContextFactoryThrowsInMajorMode(): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(ScriptException::class, ScriptException::class . '::hookMethodOutsideOfChannelContext', 'v6.8.0.0')));

        ScriptException::hookMethodOutsideOfChannelContext('render');
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testLegacyFrontendFactoryThrowsInMajorMode(): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(ScriptException::class, ScriptException::class . '::frontendBundleMissingForHookMethod', 'v6.8.0.0')));

        ScriptException::frontendBundleMissingForHookMethod('render');
    }

    public function testAccessFromScriptExecutionContextNotAllowedException(): void
    {
        $exception = ScriptException::accessFromScriptExecutionContextNotAllowed('class', 'method');

        static::assertSame('FRAMEWORK__ACCESS_FROM_SCRIPT_EXECUTION_NOT_ALLOWED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testFunctionDoesNotExistInInterfaceHookException(): void
    {
        $exception = ScriptException::functionDoesNotExistInInterfaceHook('class', 'function');

        static::assertSame('FRAMEWORK__FUNCTION_DOES_NOT_EXIST_IN_INTERFACE_HOOK', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testNoHookServiceFactoryException(): void
    {
        $exception = ScriptException::noHookServiceFactory('class');

        static::assertSame('FRAMEWORK__NO_HOOK_SERVICE_FACTORY', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testServiceNotAvailableInHookException(): void
    {
        $exception = ScriptException::serviceNotAvailableInHook('class', 'service');

        static::assertSame('FRAMEWORK__SERVICE_NOT_AVAILABLE_IN_HOOK', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testServiceAlreadyExistsException(): void
    {
        $exception = ScriptException::serviceAlreadyExists('service');

        static::assertSame('FRAMEWORK__SCRIPT_SERVICE_ALREADY_EXISTS', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testInterfaceHookExecutionNotAllowedException(): void
    {
        $exception = ScriptException::interfaceHookExecutionNotAllowed('class');

        static::assertSame('FRAMEWORK__INTERFACE_HOOK_EXECUTION_NOT_ALLOWED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testRequiredFunctionMissingInInterfaceHookException(): void
    {
        $exception = ScriptException::requiredFunctionMissingInInterfaceHook('function', 'script');

        static::assertSame('FRAMEWORK__REQUIRED_FUNCTION_MISSING_IN_INTERFACE_HOOK', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }
}
