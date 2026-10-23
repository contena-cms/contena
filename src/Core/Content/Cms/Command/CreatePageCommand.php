<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Command;

use Faker\Factory;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('cms:page:create')]
class CreatePageCommand extends Command
{
    /**
     * @var non-empty-list<string>|null
     */
    private ?array $blogs = null;

    /**
     * @var non-empty-list<string>|null
     */
    private ?array $categories = null;

    /**
     * @var non-empty-list<string>|null
     */
    private ?array $media = null;

    /**
     * @param EntityRepository<CmsPageCollection> $cmsPageRepository
     * @param EntityRepository<BlogCollection> $blogRepository
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityRepository $blogRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $mediaRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Reset all pages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();

        if ($input->getOption('reset')) {
            $this->resetPages($context);
        }

        $faker = Factory::create();

        $page = [
            'id' => Uuid::randomHex(),
            'name' => $faker->company(),
            'type' => 'landing_page',
            'blocks' => [
                [
                    'type' => 'image-text',
                    'slots' => [
                        ['type' => 'blog-box', 'slot' => 'left', 'config' => ['blogId' => $this->getRandomBlogId($context)]],
                        ['type' => 'image', 'slot' => 'right', 'config' => ['url' => $this->getRandomImageUrl()]],
                    ],
                ],
                [
                    'type' => 'image-text',
                    'slots' => [
                        ['type' => 'text', 'slot' => 'left', 'config' => ['content' => $faker->realText()]],
                        ['type' => 'blog-box', 'slot' => 'right', 'config' => ['blogId' => $this->getRandomBlogId($context)]],
                    ],
                ],
                [
                    'type' => 'image-text',
                    'slots' => [
                        ['type' => 'text', 'slot' => 'right', 'config' => ['content' => $faker->realText()]],
                        ['type' => 'image', 'slot' => 'left', 'config' => ['mediaId' => $this->getRandomMediaId($context)]],
                    ],
                ],
                [
                    'type' => 'listing',
                    'slots' => [
                        ['type' => 'blog-listing', 'slot' => 'listing', 'config' => ['categoryId' => $this->getRandomCategoryId($context)]],
                    ],
                ],
            ],
        ];

        $this->cmsPageRepository->create([$page], $context);

        $output->writeln('ID: ' . $page['id']);

        return self::SUCCESS;
    }

    private function resetPages(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(999);

        $pages = $this->cmsPageRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        if ($pages === []) {
            return;
        }

        $this->cmsPageRepository->delete($pages, $context);
    }

    private function getRandomImageUrl(): string
    {
        return 'https://source.unsplash.com/random?t=' . random_int(1, 9999);
    }

    private function getRandomBlogId(Context $context): string
    {
        if ($this->blogs === null) {
            $criteria = new Criteria();
            $criteria->setLimit(100);

            $blogIds = $this->blogRepository->searchIds($criteria, $context)->getIds();
            if ($blogIds === []) {
                throw CmsException::pageCreationFailure('No blogs found');
            }
            $this->blogs = $blogIds;
        }

        return $this->blogs[array_rand($this->blogs)];
    }

    private function getRandomCategoryId(Context $context): string
    {
        if ($this->categories === null) {
            $criteria = new Criteria();
            $criteria->setLimit(100);

            $categoryIds = $this->categoryRepository->searchIds($criteria, $context)->getIds();
            if ($categoryIds === []) {
                throw CmsException::pageCreationFailure('No categories found');
            }
            $this->categories = $categoryIds;
        }

        return $this->categories[array_rand($this->categories)];
    }

    private function getRandomMediaId(Context $context): string
    {
        if ($this->media === null) {
            $criteria = new Criteria();
            $criteria->setLimit(100);

            $mediaIds = $this->mediaRepository->searchIds($criteria, $context)->getIds();
            if ($mediaIds === []) {
                throw CmsException::pageCreationFailure('No medias found');
            }
            $this->media = $mediaIds;
        }

        return $this->media[array_rand($this->media)];
    }
}
