<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Api\MediaUploadController;
use Contena\Core\Content\Media\Api\MediaUploadV2Controller;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

/**
 * @internal
 */
#[CoversClass(MediaUploadController::class)]
#[CoversClass(MediaUploadV2Controller::class)]
class MediaUploadControllerAclTest extends TestCase
{
    /**
     * @param class-string $controller
     * @param list<string> $privileges
     */
    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresExpectedPrivileges(string $controller, string $routeName, array $privileges): void
    {
        $route = new AttributeRouteControllerLoader()->load($controller)->get($routeName);

        static::assertNotNull($route, \sprintf('Route "%s" is not defined on %s', $routeName, $controller));
        static::assertSame($privileges, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'legacy upload' => [MediaUploadController::class, 'api.action.media.upload', ['media:update']];
        yield 'rename' => [MediaUploadController::class, 'api.action.media.rename', ['media:update']];
        yield 'provide name' => [MediaUploadController::class, 'api.action.media.provide-name', ['media:read']];
        yield 'upload' => [MediaUploadV2Controller::class, 'api.action.media.upload_v2', ['media:create']];
        yield 'upload by URL' => [MediaUploadV2Controller::class, 'api.action.media.upload_v2_url', ['media:create']];
        yield 'external link' => [MediaUploadV2Controller::class, 'api.action.media.external-link', ['media:create']];
        yield 'add external thumbnails' => [MediaUploadV2Controller::class, 'api.action.media.add-external-thumbnails', ['media_thumbnail:create']];
        yield 'delete external thumbnails' => [MediaUploadV2Controller::class, 'api.action.media.delete-external-thumbnails', ['media_thumbnail:delete']];
    }
}
