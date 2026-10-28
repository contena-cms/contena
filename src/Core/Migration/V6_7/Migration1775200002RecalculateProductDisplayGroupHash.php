<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\RegisteredIndexerSubscriber;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;

/**
 * Schedules a product index pass that runs only the variant listing updater ({@see ProductIndexer::VARIANT_LISTING_UPDATER}),
 * which recalculates {@see ProductEntity::displayGroup} with the same logic as a full product
 * index. Execution is deferred until post-install/update flows process {@see IndexerQueuer}
 * (for example after {@see UpdatePostFinishEvent}), so migrations stay fast even on
 * large catalogs.
 *
 * After update, {@see RegisteredIndexerSubscriber} runs the product indexer
 * with a computed skip list ({@code array_diff} of {@see ProductIndexer::getOptions()} and the registered option names),
 * so only the variant-listing updater executes.
 *
 * CLI (other entity indexers omitted): {@code bin/console dal:refresh:index --only=product.indexer}
 *
 * @internal
 */
#[Package('framework')]
class Migration1775200002RecalculateProductDisplayGroupHash extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775200002;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'product.indexer', [ProductIndexer::VARIANT_LISTING_UPDATER]);
    }
}
