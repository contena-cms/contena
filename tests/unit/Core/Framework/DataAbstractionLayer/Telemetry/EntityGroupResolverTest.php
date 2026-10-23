<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(EntityGroupResolver::class)]
class EntityGroupResolverTest extends TestCase
{
    #[DataProvider('entityProvider')]
    public function testResolve(string $entityName, string $expected): void
    {
        static::assertSame($expected, (new EntityGroupResolver())->resolve($entityName));
    }

    public static function entityProvider(): \Generator
    {
        // exact full-name lookup takes precedence over root-token mapping
        yield 'main_category maps to category' => ['main_category', 'category'];
        yield 'property_group maps to blog' => ['property_group', 'blog'];
        yield 'property_group_option maps to blog' => ['property_group_option', 'blog'];

        // root-token (part before first underscore) mapping
        yield 'blog_price falls back to blog root' => ['blog_price', 'blog'];
        yield 'newsletter_recipient maps to user' => ['newsletter_recipient', 'user'];
        yield 'mail_template maps to content' => ['mail_template', 'content'];
        yield 'landing_page maps to content' => ['landing_page', 'content'];
        yield 'channel maps to system' => ['channel', 'system'];

        // whole name used as root token when there is no underscore
        yield 'blog without suffix maps to blog' => ['blog', 'blog'];

        // unlisted roots fall through to other
        yield 'unknown entity is other' => ['totally_unknown', 'other'];
        yield 'unknown single-token entity is other' => ['unknown', 'other'];
        yield 'plugin custom entity is other' => ['custom_entity_blog', 'other'];
    }
}
