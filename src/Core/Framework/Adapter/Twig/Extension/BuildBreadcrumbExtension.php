<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Channel\ChannelCategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
class BuildBreadcrumbExtension extends AbstractExtension
{
    /**
     * @internal
     *
     * @param ChannelRepository<EntityCollection<ChannelCategoryEntity>> $channelCategoryRepository
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly CategoryBreadcrumbBuilder $categoryBreadcrumbBuilder,
        private readonly ChannelRepository $channelCategoryRepository,
        private readonly EntityRepository $categoryRepository,
    ) {
    }

    /**
     * @phpstan-ignore contena.cnprecatedClass (not triggering deprecation to avoid polluting logs, the registered functions trigger it themselves)
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_breadcrumb_full', $this->getFullBreadcrumb(...), ['needs_context' => true]),
            new TwigFunction('sw_breadcrumb_full_by_id', $this->getFullBreadcrumbById(...), ['needs_context' => true]),
        ];
    }

    /**
     * @param array<string, mixed> $twigContext
     *
     * @return array<string, CategoryEntity|ChannelCategoryEntity>
     */
    public function getFullBreadcrumb(array $twigContext, CategoryEntity $category, Context|ChannelContext $context): array
    {
        // Methods still called in Twig behind feature flag. Deprecation is silenced to avoid polluting logs.
        $method = __METHOD__;
        Feature::silent('v6.8.0.0', static function () use ($method): void {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, $method, 'v6.8.0.0'));
        });

        if ($context instanceof Context) {
            $context = TwigContextHelper::getChannelContext($twigContext) ?? $context;
        }

        $seoBreadcrumb = $this->categoryBreadcrumbBuilder->build(
            $category,
            ($context instanceof ChannelContext) ? $context->getChannel() : null,
        );

        if ($seoBreadcrumb === null) {
            return [];
        }

        $categoryIds = array_keys($seoBreadcrumb);
        if ($categoryIds === []) {
            return [];
        }

        $criteria = new Criteria($categoryIds);
        $criteria->setTitle('breadcrumb-extension');

        if ($context instanceof ChannelContext) {
            $categories = $this->channelCategoryRepository->search($criteria, $context)->getEntities();
        } else {
            $categories = $this->categoryRepository->search($criteria, $context)->getEntities();
        }

        $breadcrumb = [];
        foreach ($categoryIds as $categoryId) {
            if ($categories->get($categoryId) === null) {
                continue;
            }

            $breadcrumb[$categoryId] = $categories->get($categoryId);
        }

        return $breadcrumb;
    }

    /**
     * @param array<string, mixed> $twigContext
     *
     * @return array<string, CategoryEntity|ChannelCategoryEntity>
     */
    public function getFullBreadcrumbById(array $twigContext, string $categoryId, Context|ChannelContext $context): array
    {
        // Methods still called in Twig behind feature flag. Deprecation is silenced to avoid polluting logs.
        $method = __METHOD__;
        Feature::silent('v6.8.0.0', static function () use ($method): void {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, $method, 'v6.8.0.0'));
        });

        if ($context instanceof Context) {
            $context = TwigContextHelper::getChannelContext($twigContext) ?? $context;
        }

        if ($context instanceof ChannelContext) {
            $category = $this->channelCategoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
        } else {
            $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
        }

        if ($category === null) {
            return [];
        }

        return $this->getFullBreadcrumb($twigContext, $category, $context);
    }
}
