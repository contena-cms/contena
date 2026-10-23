<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Service;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;

readonly class CmsFormSlotConfigResolver
{
    /**
     * @internal
     *
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param EntityRepository<LandingPageCollection> $landingPageRepository
     * @param EntityRepository<BlogCollection> $blogRepository
     * @param EntityRepository<CmsSlotCollection> $cmsSlotRepository
     */
    public function __construct(
        private EntityRepository $categoryRepository,
        private EntityRepository $landingPageRepository,
        private EntityRepository $blogRepository,
        private EntityRepository $cmsSlotRepository,
        private SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @return array{receivers: array<string, string>, message: string}
     */
    public function resolve(ChannelContext $context, ?string $slotId, ?string $navigationId, ?string $entityName): array
    {
        $slotConfig = $this->getSlotConfig($context, $slotId, $navigationId, $entityName);

        if (!\is_array($slotConfig['receivers']) || $slotConfig['receivers'] === []) {
            $slotConfig['receivers'] = [
                $this->systemConfigService->getString('core.basicInformation.email', $context->getChannelId()) => $this->systemConfigService->getString('core.basicInformation.shopName', $context->getChannelId()),
            ];
        } else {
            $slotConfig['receivers'] = \array_combine($slotConfig['receivers'], $slotConfig['receivers']);
        }

        if (!\is_string($slotConfig['message'])) {
            $slotConfig['message'] = '';
        }

        return $slotConfig;
    }

    /**
     * @return array{receivers: array<int, string>|null, message: string|null}
     */
    private function getSlotConfig(ChannelContext $context, ?string $slotId, ?string $navigationId, ?string $entityName): array
    {
        $slotConfig = ['receivers' => null, 'message' => null];

        if (!$slotId) {
            return $slotConfig;
        }

        if ($navigationId) {
            $slotConfig = $this->getSlotConfigFromEntity($context, $slotId, $navigationId, $entityName);

            if (\is_array($slotConfig['receivers']) && \is_string($slotConfig['message'])) {
                return $slotConfig;
            }
        }

        $criteria = new Criteria([$slotId]);
        $slot = $this->cmsSlotRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$slot) {
            return $slotConfig;
        }

        $config = $slot->getTranslated()['config'] ?? null;

        if (\is_array($config)) {
            if (!\is_array($slotConfig['receivers'])
                && \is_array($config['mailReceiver'] ?? null)
                && \is_array($config['mailReceiver']['value'] ?? null)
            ) {
                $slotConfig['receivers'] = $config['mailReceiver']['value'];
            }

            if (!\is_string($slotConfig['message'])
                && \is_array($config['confirmationText'] ?? null)
                && \is_string($config['confirmationText']['value'] ?? null)
            ) {
                $slotConfig['message'] = $config['confirmationText']['value'];
            }
        }

        return $slotConfig;
    }

    /**
     * @return array{receivers: array<int, string>|null, message: string|null}
     */
    private function getSlotConfigFromEntity(ChannelContext $context, string $slotId, string $navigationId, ?string $entityName = null): array
    {
        $slotConfig = ['receivers' => null, 'message' => null];

        $criteria = new Criteria([$navigationId]);

        $entity = match ($entityName) {
            BlogDefinition::ENTITY_NAME => $this->blogRepository->search($criteria, $context->getContext())->getEntities()->first(),
            LandingPageDefinition::ENTITY_NAME => $this->landingPageRepository->search($criteria, $context->getContext())->getEntities()->first(),
            default => $this->categoryRepository->search($criteria, $context->getContext())->getEntities()->first(),
        };

        if (!$entity || !$entity->getSlotConfig() || !\array_key_exists($slotId, $entity->getSlotConfig())) {
            return $slotConfig;
        }

        $config = $entity->getSlotConfig()[$slotId];

        if (!\is_array($config)) {
            return $slotConfig;
        }

        if (\is_array($config['mailReceiver'] ?? null) && \is_array($config['mailReceiver']['value'] ?? null)) {
            $slotConfig['receivers'] = $config['mailReceiver']['value'];
        }

        if (\is_array($config['confirmationText'] ?? null) && \is_string($config['confirmationText']['value'] ?? null)) {
            $slotConfig['message'] = $config['confirmationText']['value'];
        }

        return $slotConfig;
    }
}
