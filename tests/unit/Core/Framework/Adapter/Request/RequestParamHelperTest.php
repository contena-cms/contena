<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RequestParamHelper::class)]
class RequestParamHelperTest extends TestCase
{
    public function testHelper(): void
    {
        $request = new Request(
            query: ['scalar' => 'query', 'non-scalar' => ['query']],
            request: ['scalar' => 'request', 'non-scalar' => ['request']],
            attributes: ['scalar' => 'attributes', 'non-scalar' => ['attributes']],
        );

        // test fallback with scalar value
        static::assertSame('test', RequestParamHelper::get($request, 'not-existing', 'test'));

        // test fallback with non-scalar value
        static::assertSame(['test'], RequestParamHelper::get($request, 'not-existing', ['test']));

        // test fallback without default
        static::assertNull(RequestParamHelper::get($request, 'not-existing'));

        // test query takes precedence over request, attributes are ignored
        static::assertSame('query', RequestParamHelper::get($request, 'scalar'));
        static::assertSame(['query'], RequestParamHelper::get($request, 'non-scalar'));

        $request->query->remove('scalar');
        $request->query->remove('non-scalar');

        // test request value is used if query is empty
        static::assertSame('request', RequestParamHelper::get($request, 'scalar'));
        static::assertSame(['request'], RequestParamHelper::get($request, 'non-scalar'));
    }
}
