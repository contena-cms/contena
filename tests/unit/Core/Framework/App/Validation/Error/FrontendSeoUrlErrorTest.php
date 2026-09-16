<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\App\Validation\Error\FrontendSeoUrlError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FrontendSeoUrlError::class)]
class FrontendSeoUrlErrorTest extends TestCase
{
    public function testMessageKey(): void
    {
        $error = new FrontendSeoUrlError(['blog: an entity bound seo-url must not define a path']);

        static::assertSame('manifest-invalid-frontend-seo-url', $error->getMessageKey());
    }

    public function testMessageListsEveryViolation(): void
    {
        $error = new FrontendSeoUrlError([
            'blog: an entity bound seo-url must not define a path',
            'imprint: the path "account" is already used by another route',
        ]);

        static::assertSame(
            "The following frontend SEO URLs are invalid:\n"
            . "- blog: an entity bound seo-url must not define a path\n"
            . '- imprint: the path "account" is already used by another route',
            $error->getMessage()
        );
    }

    public function testMessageWithSingleViolation(): void
    {
        $error = new FrontendSeoUrlError(['blog: an entity bound seo-url requires a non-empty default-template']);

        static::assertSame(
            "The following frontend SEO URLs are invalid:\n- blog: an entity bound seo-url requires a non-empty default-template",
            $error->getMessage()
        );
    }
}
