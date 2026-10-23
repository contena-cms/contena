<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Controller\Exception\FrontendException;
use Twig\Error\Error as TwigError;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(FrontendException::class)]
class FrontendExceptionTest extends TestCase
{
    public function testRenderViewException(): void
    {
        $parameters = [
            'param' => 'Param',
            'context' => Context::createDefaultContext(),
        ];

        $view = 'test.html.twig';

        $twigError = new TwigError('Error message', 5, new Source('<div>ExampleCode</div>', $view, $view));

        $res = FrontendException::renderViewException($view, $twigError, $parameters);

        static::assertSame(500, $res->getStatusCode());
        static::assertSame('FRONTEND__CAN_NOT_RENDER_VIEW', $res->getErrorCode());
        static::assertSame('Can not render test.html.twig view: Error message in "test.html.twig" at line 5 with these parameters: {"param":"Param"}', $res->getMessage());
        static::assertSame(5, $res->getLine());
        static::assertSame('test.html.twig', $res->getFile());
    }

    public function testRenderViewExceptionUsesCustomAppErrorCodeForExternalIssues(): void
    {
        $parameters = [
            'param' => 'Param',
            'context' => Context::createDefaultContext(),
        ];

        $view = 'test.html.twig';
        $path = 'platform/custom/apps/ElleChildTheme/Resources/views/frontend/layout/footer/footer.html.twig';

        $twigError = new TwigError('Error message', 5, new Source('<div>ExampleCode</div>', $view, $path));
        $exception = FrontendException::renderViewException($view, $twigError, $parameters);

        static::assertSame(500, $exception->getStatusCode());
        static::assertSame('FRONTEND__CAN_NOT_RENDER_CUSTOM_APP_VIEW', $exception->getErrorCode());
        static::assertSame('Can not render test.html.twig view: Error message in "test.html.twig" at line 5 with these parameters: {"param":"Param"}', $exception->getMessage());
        static::assertSame(5, $exception->getLine());
        static::assertSame($path, $exception->getFile());
    }

    public function testUnSupportFrontendResponse(): void
    {
        $res = FrontendException::unSupportFrontendResponse();

        static::assertSame(500, $res->getStatusCode());
        static::assertSame('FRONTEND__UN_SUPPORT_FRONTEND_RESPONSE', $res->getErrorCode());
        static::assertSame('Symfony render implementation changed. Providing a response is no longer supported', $res->getMessage());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDontHaveTwigInjected(): void
    {
        $res = FrontendException::dontHaveTwigInjected('Example\Class');

        static::assertSame(500, $res->getStatusCode());
        static::assertSame('FRONTEND__CLASS_DONT_HAVE_TWIG_INJECTED', $res->getErrorCode());
        static::assertSame('Class Example\Class does not have twig injected. Add to your service definition a method call to setTwig with the twig instance', $res->getMessage());
    }

    public function testNoRequestProvided(): void
    {
        $res = FrontendException::noRequestProvided();

        static::assertSame(500, $res->getStatusCode());
        static::assertSame('FRONTEND__NO_REQUEST_PROVIDED', $res->getErrorCode());
        static::assertSame(
            'No request is available.This controller action require an active request context.',
            $res->getMessage()
        );
    }
}
