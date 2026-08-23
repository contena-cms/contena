<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Channel\MediaRouteResponse;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;

/**
 * @internal
 */
#[CoversClass(MediaRouteResponse::class)]
class MediaRouteResponseTest extends TestCase
{
    public function testMediaRouterIsCorrectlyConstructed(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('testMediaId');
        $mediaEntity->setPath('testPath');

        $mediaCollection = new MediaCollection();
        $mediaCollection->add($mediaEntity);

        $mediaRouteResponse = new MediaRouteResponse($mediaCollection);

        static::assertSame($mediaCollection, $mediaRouteResponse->getMediaCollection());
    }
}
