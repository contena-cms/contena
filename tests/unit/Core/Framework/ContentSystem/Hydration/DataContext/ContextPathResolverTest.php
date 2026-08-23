<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Test\Stub\ContentSystem\StubPathStruct;

/**
 * @internal
 */
#[CoversClass(ContextPathResolver::class)]
class ContextPathResolverTest extends TestCase
{
    private ContextPathResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ContextPathResolver();
    }

    #[TestDox('parses key with single segment after root, returning tail segments')]
    public function testParseContextKeyWithSingleDottedKey(): void
    {
        $result = $this->resolver->parseContextKey('blog.cover');

        static::assertSame(['cover'], $result);
    }

    #[TestDox('parses key with no dot, returning empty array')]
    public function testParseContextKeyWithNoDot(): void
    {
        $result = $this->resolver->parseContextKey('blog');

        static::assertSame([], $result);
    }

    #[TestDox('resolves empty path by returning data as-is')]
    public function testResolvePathWithEmptyPathReturnsDataAsIs(): void
    {
        $struct = new StubPathStruct('hello');

        static::assertSame($struct, $this->resolver->resolvePath($struct, [], false, 'blog', 'elem-1'));
        static::assertNull($this->resolver->resolvePath(null, [], false, 'blog', 'elem-1'));
    }

    #[TestDox('resolves single-segment path on a Struct, returning the property value')]
    public function testResolvePathResolvesDirectStructProperty(): void
    {
        $struct = new StubPathStruct('contena');

        $result = $this->resolver->resolvePath($struct, ['name'], false, 'blog.name', 'elem-1');

        static::assertSame('contena', $result);
    }

    #[TestDox('resolves nested Struct path, returning deeply nested property')]
    public function testResolvePathResolvesNestedStructProperty(): void
    {
        $child = new StubPathStruct('child-name');
        $parent = new StubPathStruct('parent-name', $child);

        $result = $this->resolver->resolvePath($parent, ['child', 'name'], false, 'blog.child.name', 'elem-1');

        static::assertSame('child-name', $result);
    }

    /**
     * @param list<string> $path
     */
    #[DataProvider('unresolvedPathNotRequiredProvider')]
    #[TestDox('returns null for unresolvable path when not required: $_dataName')]
    public function testResolvePathUnresolvableNotRequiredReturnsNull(?Struct $data, array $path, string $fullPath): void
    {
        $result = $this->resolver->resolvePath($data, $path, false, $fullPath, 'elem-1');

        static::assertNull($result);
    }

    /**
     * @return iterable<string, array{Struct|null, list<string>, string}>
     */
    public static function unresolvedPathNotRequiredProvider(): iterable
    {
        yield 'null base data' => [null, ['cover'], 'blog.cover'];
        yield 'missing property on struct' => [new StubPathStruct('test'), ['missing'], 'blog.missing'];
        yield 'non-struct intermediate value' => [new StubPathStruct(null, null, 'plain-string'), ['nonStructProp', 'deeper'], 'blog.nonStructProp.deeper'];
        yield 'null intermediate value' => [new StubPathStruct(null, null), ['child', 'name'], 'blog.child.name'];
    }

    /**
     * @param list<string> $path
     */
    #[DataProvider('unresolvedPathRequiredProvider')]
    #[TestDox('throws for unresolvable path when required: $_dataName')]
    public function testResolvePathUnresolvableRequiredThrows(?Struct $data, array $path, string $fullPath, ContentSystemException $exception): void
    {
        $this->expectExceptionObject($exception);

        $this->resolver->resolvePath($data, $path, true, $fullPath, 'elem-1');
    }

    /**
     * @return iterable<string, array{Struct|null, list<string>, string, ContentSystemException}>
     */
    public static function unresolvedPathRequiredProvider(): iterable
    {
        yield 'null base data' => [
            null, ['cover'], 'blog.cover',
            ContentSystemException::contextPathNotResolvable('blog.cover', 'elem-1', 'Base context data is null'),
        ];
        yield 'missing property on struct' => [
            new StubPathStruct('test'), ['missing'], 'blog.missing',
            ContentSystemException::contextPathNotResolvable('blog.missing', 'elem-1', 'Property \'missing\' does not exist at path \'missing\''),
        ];
        yield 'non-struct intermediate value' => [
            new StubPathStruct(null, null, 'plain-string'), ['nonStructProp', 'deeper'], 'blog.nonStructProp.deeper',
            ContentSystemException::contextPathNotResolvable('blog.nonStructProp.deeper', 'elem-1', 'Intermediate value at \'nonStructProp\' is not a Struct instance'),
        ];
        yield 'null intermediate value' => [
            new StubPathStruct(null, null), ['child', 'name'], 'blog.child.name',
            ContentSystemException::contextPathNotResolvable('blog.child.name', 'elem-1', 'Intermediate value at \'child\' is null'),
        ];
    }

    #[TestDox('matches identical provider and consumer keys')]
    public function testMatchesReturnsTrueForExactMatch(): void
    {
        static::assertTrue($this->resolver->matches('blog', 'blog'));
    }

    #[TestDox('matches when consumer key is a subpath of provider key')]
    public function testMatchesReturnsTrueWhenConsumerIsSubpath(): void
    {
        static::assertTrue($this->resolver->matches('blog', 'blog.cover'));
    }

    #[TestWithJson('["category","blog"]')]
    #[TestWithJson('["prod","blog"]')]
    #[TestWithJson('["blog","blogs.cover"]')]
    #[TestDox('returns false for non-matching key pair')]
    public function testMatchesReturnsFalseForNonMatchingKeyPair(string $provider, string $consumer): void
    {
        static::assertFalse($this->resolver->matches($provider, $consumer));
    }

    #[TestDox('extracts base key from dotted path')]
    public function testExtractBaseKeyFromDottedPath(): void
    {
        $result = $this->resolver->extractBaseKey('blog.cover');

        static::assertSame('blog', $result);
    }
}
