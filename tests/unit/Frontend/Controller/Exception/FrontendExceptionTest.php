<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
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

        $exception = FrontendException::renderViewException($view, $twigError, $parameters);

        static::assertSame(500, $exception->getStatusCode());
        static::assertSame('FRONTEND__CAN_NOT_RENDER_VIEW', $exception->getErrorCode());
        static::assertSame('Can not render test.html.twig view: Error message in "test.html.twig" at line 5 with these parameters: {"param":"Param"}', $exception->getMessage());
        static::assertSame(5, $exception->getLine());
        static::assertSame('test.html.twig', $exception->getFile());
    }

    public function testNoRequestProvided(): void
    {
        $exception = FrontendException::noRequestProvided();

        static::assertSame(500, $exception->getStatusCode());
        static::assertSame('FRONTEND__NO_REQUEST_PROVIDED', $exception->getErrorCode());
        static::assertSame(
            'No request is available. This controller action requires an active request context.',
            $exception->getMessage()
        );
    }
}
