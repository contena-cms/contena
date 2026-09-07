<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context;

use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerBaseKeyResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ConsumerBaseKeyResolver::class)]
class ConsumerBaseKeyResolverTest extends TestCase
{
    private ConsumerBaseKeyResolver $consumerBaseKey;

    protected function setUp(): void
    {
        $this->consumerBaseKey = new ConsumerBaseKeyResolver();
    }

    #[DataProvider('keyProvider')]
    #[TestDox('reduces a property key to the write boundary\'s uniqueness axis')]
    public function testResolveReducesKeyToItsBaseKey(string $key, string $expected): void
    {
        static::assertSame($expected, $this->consumerBaseKey->resolve($key));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function keyProvider(): iterable
    {
        yield 'undotted key returns itself' => ['blog', 'blog'];
        yield 'dotted key returns its first segment' => ['blog.name', 'blog'];
        yield 'multi-dot key returns its first segment' => ['blog.name.short', 'blog'];
        yield 'leading-dot key returns the empty first segment' => ['.blog', ''];
        yield 'empty key returns itself' => ['', ''];
    }
}
