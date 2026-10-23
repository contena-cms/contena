<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class CmsPageBeforeDeleteSubscriberTest extends TestCase
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

    public function testDeleteCmsPageDoesNotThrow(): void
    {
        $defaultCmsPageId = Uuid::randomHex();
        $this->createCmsPage($defaultCmsPageId);

        // create cms page to delete
        $cmsPageId = Uuid::randomHex();
        $this->createCmsPage($cmsPageId);

        $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $defaultCmsPageId, null);
        $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $defaultCmsPageId, TestDefaults::CHANNEL);

        $id = $this->cmsPageRepository->searchIds(new Criteria([$cmsPageId]), Context::createDefaultContext())->firstId();
        static::assertSame($cmsPageId, $id);

        // delete cms page which is not default
        $this->cmsPageRepository->delete([['id' => $cmsPageId]], Context::createDefaultContext());

        $id = $this->cmsPageRepository->searchIds(new Criteria([$cmsPageId]), Context::createDefaultContext())->firstId();
        static::assertNull($id);
    }

    public function testDeleteOverallDefaultCmsPageThrow(): void
    {
        $cmsPageId = Uuid::randomHex();
        $exceptionWasThrown = false;
        $this->createCmsPage($cmsPageId);

        // set cms page id as overall default
        $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $cmsPageId, null);

        // at this point we do not differentiate whether this is an overall default or not
        try {
            $this->cmsPageRepository->delete([['id' => $cmsPageId]], Context::createDefaultContext());
        } catch (CmsException $exception) {
            static::assertSame(CmsException::DELETION_OF_DEFAULT_CODE, $exception->getErrorCode());
            $exceptionWasThrown = true;
        } finally {
            if (!$exceptionWasThrown) {
                static::fail('Expected exception with error code ' . CmsException::DELETION_OF_DEFAULT_CODE . ' to be thrown.');
            }
        }
    }

    public function testDeleteDefaultCmsPageThrow(): void
    {
        $cmsPageId = Uuid::randomHex();
        $exceptionWasThrown = false;
        $this->createCmsPage($cmsPageId);

        // set cms page id as sales channel specific default
        $this->systemConfigService->set(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $cmsPageId, TestDefaults::CHANNEL);

        try {
            $this->cmsPageRepository->delete([['id' => $cmsPageId]], Context::createDefaultContext());
        } catch (CmsException $exception) {
            static::assertSame(CmsException::DELETION_OF_DEFAULT_CODE, $exception->getErrorCode());
            $exceptionWasThrown = true;
        } finally {
            if (!$exceptionWasThrown) {
                static::fail('Expected exception with error code ' . CmsException::DELETION_OF_DEFAULT_CODE . ' to be thrown.');
            }
        }
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
