<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Cms\Exception\PageNotFoundException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class CmsPageBeforeDefaultChangeSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CmsPageCollection>
     */
    private EntityRepository $cmsPageRepository;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cmsPageRepository = static::getContainer()->get('cms_page.repository');
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    #[DataProvider('validDefaultCmsPageDataProvider')]
    public function testSetDefaultDoesNotThrow(string $validCmsPageId, ?string $channelId): void
    {
        $this->createCmsPage($validCmsPageId);
        $error = null;
        $message = '';

        try {
            $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $validCmsPageId, $channelId);
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    public static function validDefaultCmsPageDataProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'validCmsPageId with salesChanelId null' => [
            'validCmsPageId' => $ids->get('validCmsPageId'),
            'channelId' => null,
        ];

        yield 'validCmsPageId with default salesChanelId' => [
            'validCmsPageId' => $ids->get('validCmsPageId'),
            'channelId' => TestDefaults::CHANNEL,
        ];
    }

    #[DataProvider('invalidDefaultCmsPageDataProvider')]
    public function testSetInvalidDefaultThrow(string $invalidCmsPageId, ?string $channelId): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            $this->expectExceptionObject(CmsException::pageNotFound($invalidCmsPageId));
        } else {
            $this->expectExceptionObject(new PageNotFoundException($invalidCmsPageId));
        }
        $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $invalidCmsPageId, $channelId);
    }

    public static function invalidDefaultCmsPageDataProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'invalidCmsPageId with salesChanelId null' => [
            'invalidCmsPageId' => $ids->get('invalidCmsPageId'),
            'channelId' => null,
        ];

        yield 'invalidCmsPageId with default salesChanelId' => [
            'invalidCmsPageId' => $ids->get('invalidCmsPageId'),
            'channelId' => TestDefaults::CHANNEL,
        ];
    }

    public function testDeleteChannelDefaultDoesNotThrow(): void
    {
        $cmsPage = Uuid::randomHex();
        $this->createCmsPage($cmsPage);
        $error = null;
        $message = '';

        try {
            // set sales channel specific default
            $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $cmsPage, TestDefaults::CHANNEL);

            // expect to be able to delete the default
            $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, null, TestDefaults::CHANNEL);
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    public function testDeleteOverallDefaultThrow(): void
    {
        $cmsPage = Uuid::randomHex();
        $this->createCmsPage($cmsPage);

        // set overall default
        $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $cmsPage);

        $this->expectExceptionObject(CmsException::overallDefaultSystemConfigDeletion($cmsPage));
        $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, null);
    }

    private function createCmsPage(string $cmsPageId): void
    {
        $cmsPage = [
            'id' => $cmsPageId,
            'name' => 'test page',
            'type' => 'blog_detail',
        ];

        $this->cmsPageRepository->create([$cmsPage], Context::createDefaultContext());
    }
}
