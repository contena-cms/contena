<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\NumberRange\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\NumberRange\Api\NumberRangeController;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(NumberRangeController::class)]
class NumberRangeControllerTest extends TestCase
{
    public function testReserveUsesValueGenerator(): void
    {
        $context = Context::createDefaultContext();

        $valueGenerator = $this->createMock(AbstractNumberRangeValueGenerator::class);
        $valueGenerator->expects($this->once())
            ->method('getValue')
            ->with('order', $context, 'sales-channel-id', true)
            ->willReturn('1000');

        $response = (new NumberRangeController($valueGenerator))->reserve(
            'order',
            'sales-channel-id',
            $context,
            new Request(['preview' => '1'])
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"number":"1000"}', $response->getContent());
    }

    public function testPreviewPatternByNumberRangeIdUsesValueGenerator(): void
    {
        $numberRangeId = Uuid::randomHex();

        $valueGenerator = $this->createMock(AbstractNumberRangeValueGenerator::class);
        $valueGenerator->expects($this->once())
            ->method('previewPatternByNumberRangeId')
            ->with($numberRangeId, 'ORD-{n}', 10)
            ->willReturn('ORD-10');

        $response = (new NumberRangeController($valueGenerator))->previewPatternByNumberRange(
            $numberRangeId,
            new Request(['pattern' => 'ORD-{n}', 'start' => '10'])
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"number":"ORD-10"}', $response->getContent());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedPreviewPatternUsesLegacyValueGenerator(): void
    {
        $valueGenerator = $this->createMock(AbstractNumberRangeValueGenerator::class);
        $valueGenerator->expects($this->once())
            ->method('previewPattern')
            ->with('user', 'C-{n}', 0)
            ->willReturn('C-1');

        $response = (new NumberRangeController($valueGenerator))->previewPattern(
            'user',
            new Request(['pattern' => 'C-{n}'])
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"number":"C-1"}', $response->getContent());
    }

    public function testDeprecatedPreviewPatternThrowsWhenMajorFeatureIsActive(): void
    {
        $valueGenerator = $this->createMock(AbstractNumberRangeValueGenerator::class);
        $valueGenerator->expects($this->never())
            ->method('previewPattern');

        $this->expectException(FeatureException::class);

        (new NumberRangeController($valueGenerator))->previewPattern('user', new Request());
    }

    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresNumberRangeReadPrivilege(string $routeName): void
    {
        $route = (new AttributeRouteControllerLoader())->load(NumberRangeController::class)->get($routeName);

        static::assertNotNull(
            $route,
            \sprintf('Route "%s" is not defined on %s', $routeName, NumberRangeController::class)
        );
        static::assertSame(['number_range:read'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'reserving a number consumes the range state' => ['api.action.number-range.reserve'];
        yield 'previewing a pattern exposes the range configuration' => ['api.action.number-range.preview-pattern'];
        yield 'previewing a pattern by id exposes the range configuration' => ['api.action.number-range.preview-pattern-by-id'];
    }
}
