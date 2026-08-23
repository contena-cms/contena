<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Service;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Service\SitemapLister;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\Asset\Package;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(SitemapLister::class)]
class SitemapListerTest extends TestCase
{
    public function testListsFilesWithoutDomainId(): void
    {
        $context = Generator::generateChannelContext();

        $filesystem = static::createStub(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes('sitemap/channel-' . $context->getChannelId() . '-' . $context->getLanguageId() . '/' . $context->getChannelId(), 0, null, null, null),
        ]));

        $package = static::createStub(Package::class);
        $package->method('getUrl')->willReturnCallback(static function (string $path) {
            return $path;
        });

        $sitemapLister = new SitemapLister($filesystem, $package, new NativeClock());

        $sitemaps = $sitemapLister->getSitemaps($context);

        static::assertCount(1, $sitemaps);
    }

    public function testSitemapWithMultipleDomainsUseCorrectDomains(): void
    {
        $context = Generator::generateChannelContext();

        $domains = new ChannelDomainCollection();

        $defaultDomainUrl = 'https://default-sitemap.de';
        $domainUrl = 'https://test-sitemap.de';

        $defaultDomainId = Uuid::randomHex();
        $defaultDomain = new ChannelDomainEntity();
        $defaultDomain->setId($defaultDomainId);
        $defaultDomain->setUrl($defaultDomainUrl);
        $defaultDomain->setLanguageId($context->getLanguageId());

        $domains->add($defaultDomain);

        $domainId = Uuid::randomHex();
        $domain = new ChannelDomainEntity();
        $domain->setId($domainId);
        $domain->setUrl($domainUrl);
        $domain->setLanguageId($context->getLanguageId());

        $domains->add($domain);

        $context->getChannel()->setDomains($domains);

        $filesystem = static::createStub(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes('sitemap/channel-' . $context->getChannelId() . '-' . $context->getLanguageId() . '/' . $context->getChannelId() . '-' . $defaultDomainId, 0, null, null, null),
            new FileAttributes('sitemap/channel-' . $context->getChannelId() . '-' . $context->getLanguageId() . '/' . $context->getChannelId() . '-' . $domainId, 0, null, null, null),
        ]));

        $package = static::createStub(Package::class);
        $package->method('getUrl')->willReturnCallback(static function (string $path) {
            return $path;
        });

        $sitemapLister = new SitemapLister($filesystem, $package, new NativeClock());

        $sitemaps = $sitemapLister->getSitemaps($context);

        static::assertCount(2, $sitemaps);
        static::assertStringStartsWith($defaultDomainUrl, $sitemaps[0]->getFilename());
        static::assertStringStartsWith($domainUrl, $sitemaps[1]->getFilename());
    }
}
