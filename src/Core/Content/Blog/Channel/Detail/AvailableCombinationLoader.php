<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Detail;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Contena\Core\Content\Blog\Stock\AbstractStockStorage;
use Contena\Core\Content\Blog\Stock\StockLoadRequest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;

class AvailableCombinationLoader extends AbstractAvailableCombinationLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractStockStorage $stockStorage,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function getDecorated(): AbstractAvailableCombinationLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function loadCombinations(string $blogId, ChannelContext $channelContext): AvailableCombinationResult
    {
        $combinations = $this->getCombinations(
            $blogId,
            $channelContext->getContext(),
            $channelContext->getChannelId()
        );

        $stocks = $this->stockStorage->load(
            new StockLoadRequest(array_keys($combinations)),
            $channelContext
        );

        $result = new AvailableCombinationResult();
        $hideCloseoutBlogsWhenOutOfStock = $this->systemConfigService->getBool(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            $channelContext->getChannelId()
        );

        foreach ($combinations as $id => $combination) {
            try {
                $options = json_decode((string) $combination['options'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $available = (bool) $combination['available'];
            $isCloseout = (bool) $combination['isCloseout'];
            $stockData = $stocks->getStockForBlogId($id);

            if ($stockData !== null) {
                $available = $stockData->available;
            }

            if ($hideCloseoutBlogsWhenOutOfStock && $isCloseout && !$available) {
                continue;
            }

            $result->addCombination($options, $available);
        }

        return $result;
    }

    /**
     * @return array<string, array{options: string, blogNumber: string, available: string, isCloseout: string}>
     */
    private function getCombinations(string $blogId, Context $context, string $channelId): array
    {
        $parent = $this->fetchParent($blogId, $context);

        $query = $this->connection->createQueryBuilder();
        $query->from('blog');

        $query->andWhere('blog.parent_id = :id');
        $query->andWhere('blog.version_id = :versionId');
        $query->andWhere('IFNULL(blog.active, :parentActive) = :active');
        $query->andWhere('blog.option_ids IS NOT NULL');

        $query->setParameter('id', Uuid::fromHexToBytes($blogId));
        $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter('active', true);
        $query->setParameter('parentActive', $parent['active'], ParameterType::BOOLEAN);
        $query->setParameter('parentIsCloseout', $parent['is_closeout'], ParameterType::BOOLEAN);

        $query->innerJoin('blog', 'blog_visibility', 'visibilities', 'blog.visibilities = visibilities.blog_id');
        $query->andWhere('visibilities.channel_id = :channelId');
        $query->setParameter('channelId', Uuid::fromHexToBytes($channelId));

        $query->select(
            'LOWER(HEX(blog.id))',
            'blog.option_ids as options',
            'blog.blog_number as blogNumber',
            'blog.available',
            'IFNULL(blog.is_closeout, :parentIsCloseout) as isCloseout',
        );

        $combinations = $query->executeQuery()->fetchAllAssociative();

        /** @var array<string, array{options: string, blogNumber: string, available: string, isCloseout: string}> $unique */
        $unique = FetchModeHelper::groupUnique($combinations);

        return $unique;
    }

    /**
     * @return array{active: string|null, is_closeout: string|null}
     */
    private function fetchParent(string $blogId, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('active', 'is_closeout');
        $query->from('blog');
        $query->where('id = :id');
        $query->andWhere('version_id = :versionId');

        $query->setParameter('id', Uuid::fromHexToBytes($blogId));
        $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()));

        $parent = $query->executeQuery()->fetchAssociative();
        if ($parent === false) {
            return ['active' => null, 'is_closeout' => null];
        }

        return [
            'active' => $parent['active'],
            'is_closeout' => $parent['is_closeout'],
        ];
    }
}
