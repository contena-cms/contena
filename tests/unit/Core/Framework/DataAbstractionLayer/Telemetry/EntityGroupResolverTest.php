<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;

/**
 * @internal
 */
#[CoversClass(EntityGroupResolver::class)]
class EntityGroupResolverTest extends TestCase
{
    #[DataProvider('entityProvider')]
    public function testResolve(string $entityName, string $expected): void
    {
        static::assertSame($expected, new EntityGroupResolver()->resolve($entityName));
    }

    public static function entityProvider(): \Generator
    {
        // exact full-name lookup takes precedence over root-token mapping
        yield 'blog_main_category maps to category' => ['blog_main_category', 'category'];
        yield 'header_content_layout maps to content' => ['header_content_layout', 'content'];
        yield 'footer_content_layout maps to content' => ['footer_content_layout', 'content'];

        // root-token (part before first underscore) mapping
        yield 'blog_media falls back to blog root' => ['blog_media', 'blog'];
        yield 'member_address maps to member' => ['member_address', 'member'];
        yield 'mail_template maps to content' => ['mail_template', 'content'];
        yield 'landing_page maps to content' => ['landing_page', 'content'];
        yield 'content_layout maps to content' => ['content_layout', 'content'];
        yield 'channel maps to system' => ['channel', 'system'];

        // whole name used as root token when there is no underscore
        yield 'blog without suffix maps to blog' => ['blog', 'blog'];

        // unlisted roots fall through to other
        yield 'unknown entity is other' => ['totally_unknown', 'other'];
        yield 'unknown single-token entity is other' => ['unknown', 'other'];
        yield 'plugin entity is other' => ['ct_example_entry', 'other'];
    }
}
