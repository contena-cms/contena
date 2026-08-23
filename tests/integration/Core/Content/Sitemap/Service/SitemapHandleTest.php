<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Service;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Sitemap\Service\SitemapHandle;
use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
class SitemapHandleTest extends TestCase
{
    use KernelTestBehaviour;

    private ?SitemapHandle $handle = null;

    public function testWriteWithoutFinish(): void
    {
        $url = new Url();
        $url->setLoc('https://contena.cn');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects($this->never())->method('write');

        $this->handle = new SitemapHandle(
            $fileSystem,
            $this->getContext(),
            static::getContainer()->get('event_dispatcher')
        );

        $this->handle->write([
            $url,
        ]);
    }

    public function testWrite(): void
    {
        $url = new Url();
        $url->setLoc('https://contena.cn');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects($this->once())->method('write');

        $this->handle = new SitemapHandle(
            $fileSystem,
            $this->getContext(),
            static::getContainer()->get('event_dispatcher')
        );

        $this->handle->write([$url]);
        $this->handle->finish();
    }

    public function testWrite101kItems(): void
    {
        $url = new Url();
        $url->setLoc('https://contena.cn');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $list = [];

        for ($i = 1; $i <= 101000; ++$i) {
            $list[] = clone $url;
        }

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects($this->atLeast(3))->method('write');

        $this->handle = new SitemapHandle(
            $fileSystem,
            $this->getContext(),
            static::getContainer()->get('event_dispatcher')
        );

        $this->handle->write($list);
        $this->handle->finish();
    }

    private function getContext(): ChannelContext
    {
        return Generator::generateChannelContext();
    }
}
