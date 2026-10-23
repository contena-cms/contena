<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Cms;

use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Page\Cms\DefaultMediaResolver;

/**
 * @internal
 */
class DefaultMediaResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private DefaultMediaResolver $mediaResolver;

    private Stub&AbstractDefaultMediaResolver $decorated;

    protected function setUp(): void
    {
        $packages = static::getContainer()->get('assets.packages');

        $translator = static::createConfiguredStub(
            Translator::class,
            [
                'trans' => 'foobar',
            ]
        );

        $this->decorated = static::createStub(AbstractDefaultMediaResolver::class);
        $this->mediaResolver = new DefaultMediaResolver($this->decorated, $translator, $packages);
    }

    public function testGetDefaultMediaEntityWithoutValidFileName(): void
    {
        $this->decorated->method('getDefaultCmsMediaEntity')->willReturn(null);
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('this/file/does/not/exists');

        static::assertNull($media);
    }

    public function testGetDefaultMediaEntityWithValidFileName(): void
    {
        $this->decorated->method('getDefaultCmsMediaEntity')->willReturn(new MediaEntity());
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/frontend/assets/default/cms/contena.jpg');

        static::assertInstanceOf(MediaEntity::class, $media);

        // ensure url and translations are set correctly
        static::assertStringContainsString('bundles/frontend/assets/default/cms/contena.jpg', $media->getUrl());
        static::assertSame('foobar', $media->getTranslated()['title']);
        static::assertSame('foobar', $media->getTranslated()['alt']);
    }
}
