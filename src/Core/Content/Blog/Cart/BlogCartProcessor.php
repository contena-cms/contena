<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\CartDataCollectorInterface;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\CartProcessorInterface;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Contena\Core\Checkout\Cart\LineItem\CartDataCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\LineItem\QuantityInformation;
use Contena\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Contena\Core\Checkout\CheckoutPermissions;
use Contena\Core\Content\Blog\Events\BlogCartDataContextHashEvent;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Price\AbstractBlogPriceCalculator;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\State;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Tax\TaxEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BlogCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    final public const CUSTOM_PRICE = 'customPrice';

    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::ALLOW_BLOG_PRICE_OVERWRITES}
     */
    final public const ALLOW_BLOG_PRICE_OVERWRITES = CheckoutPermissions::ALLOW_BLOG_PRICE_OVERWRITES;

    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::ALLOW_BLOG_PRICE_OVERWRITES}
     */
    final public const ALLOW_BLOG_LABEL_OVERWRITES = CheckoutPermissions::ALLOW_BLOG_LABEL_OVERWRITES;

    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::SKIP_BLOG_RECALCULATION}
     */
    final public const SKIP_BLOG_RECALCULATION = CheckoutPermissions::SKIP_BLOG_RECALCULATION;

    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::SKIP_BLOG_STOCK_VALIDATION}
     */
    final public const SKIP_BLOG_STOCK_VALIDATION = CheckoutPermissions::SKIP_BLOG_STOCK_VALIDATION;

    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::KEEP_INACTIVE_BLOG}
     */
    final public const KEEP_INACTIVE_BLOG = CheckoutPermissions::KEEP_INACTIVE_BLOG;

    /**
     * @internal
     */
    public function __construct(
        private readonly BlogGatewayInterface $blogGateway,
        private readonly QuantityPriceCalculator $calculator,
        private readonly BlogFeatureBuilder $featureBuilder,
        private readonly AbstractBlogPriceCalculator $priceCalculator,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, ChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::blog::collect', function () use ($data, $original, $context, $behavior): void {
            $lineItems = $this->getBlogs($original->getLineItems());

            $items = array_column($lineItems, 'item');

            $hash = $this->getDataContextHash($data, $original, $context, $behavior);

            // find blogs in original cart which requires data from gateway
            $ids = $this->getNotCompleted($data, $items, $hash);

            if ($ids !== []) {
                // fetch missing data over gateway
                $blogs = $this->blogGateway->get($ids, $context);

                // add blogs to data collection
                foreach ($blogs as $blog) {
                    $data->set($this->getDataKey($blog->getId()), $blog);
                }
            }

            // refresh data timestamp to prevent unnecessary gateway calls
            foreach ($items as $lineItem) {
                $blog = $data->get($this->getDataKey($lineItem->getReferencedId() ?: ''));

                // blog was fetched, update timestamp to not fetch it again
                if ($blog instanceof BlogEntity) {
                    $lineItem->setDataTimestamp($blog->getUpdatedAt() ?? $blog->getCreatedAt());
                // we have asked for this blog, but we didn't get it back, so we need to remove it
                } elseif (\in_array($lineItem->getReferencedId(), $ids, true)) {
                    $lineItem->setDataTimestamp(null);
                }

                // no matter if we fetched data or not, we need to set the hash to all blogs in case it changed
                // so the next time we need to calculate and there is no data, we know to fetch it again
                $lineItem->setDataContextHash($hash);
            }

            // run price calculator in batch
            $this->recalculate($items, $data, $context, $behavior);

            foreach ($lineItems as $match) {
                // enrich all blogs in original cart
                $this->enrich($match['item'], $data, $behavior);

                // remove "parent" blogs which should never be displayed in frontend
                $this->validateParents($match['item'], $data, $match['scope']);

                // validate data timestamps that inactive blogs (or not assigned to sales channel) are removed
                $this->validateTimestamp($match['item'], $original, $data, $behavior, $match['scope']);

                // validate availability of the blog stock
                $this->validateStock($match['item'], $original, $match['scope'], $behavior);
            }

            $this->featureBuilder->prepare($items, $data, $context);
        }, 'cart');
    }

    /**
     * @throws CartException
     */
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, ChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::blog::process', function () use ($data, $original, $toCalculate, $context): void {
            $items = $original->getLineItems()->filterFlatByType(LineItem::BLOG_LINE_ITEM_TYPE);

            foreach ($items as $item) {
                $definition = $item->getPriceDefinition();

                if (!$definition instanceof QuantityPriceDefinition) {
                    throw CartException::missingLineItemPrice($item->getId());
                }
                $definition->setQuantity($item->getQuantity());

                $item->setPrice($this->calculator->calculate($definition, $context));
                $isDownloadLineItem = $item->isBlogType(BlogDefinition::TYPE_DIGITAL);

                if (!Feature::isActive('v6.8.0.0')) {
                    Feature::callSilentIfInactive('v6.8.0.0', static function () use ($item, &$isDownloadLineItem): void {
                        $isDownloadLineItem = $isDownloadLineItem || $item->hasState(State::IS_DOWNLOAD);
                    });
                }

                $item->setShippingCostAware(!$isDownloadLineItem);
            }

            $this->featureBuilder->add($items, $data, $context);

            // handle all blogs which stored in root level
            $items = $original->getLineItems()->filterType(LineItem::BLOG_LINE_ITEM_TYPE);

            foreach ($items as $item) {
                $toCalculate->add($item);
            }
        }, 'cart');
    }

    /**
     * @return list<array{'item': LineItem, 'scope': LineItemCollection}>
     */
    private function getBlogs(LineItemCollection $items): array
    {
        $matches = [];
        foreach ($items as $item) {
            if ($item->getType() === LineItem::BLOG_LINE_ITEM_TYPE) {
                $matches[] = ['item' => $item, 'scope' => $items];
            }

            $nested = $this->getBlogs($item->getChildren());

            foreach ($nested as $match) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    private function validateTimestamp(LineItem $item, Cart $cart, CartDataCollection $data, CartBehavior $behavior, LineItemCollection $items): void
    {
        $blog = $data->get(
            $this->getDataKey((string) $item->getReferencedId())
        );

        // blog data was never detected and the blog is not inside the data collection
        if ($blog !== null || $item->getDataTimestamp() !== null) {
            return;
        }

        if ($behavior->hasPermission(self::KEEP_INACTIVE_BLOG)) {
            return;
        }

        $cart->addErrors(new BlogNotFoundError($item->getLabel() ?: $item->getId()));

        $items->remove($item->getId());

        foreach ($cart->getDeliveries() as $delivery) {
            foreach ($delivery->getPositions() as $position) {
                if ($position->getIdentifier() !== $item->getId()) {
                    continue;
                }

                $delivery->getPositions()->remove($position->getIdentifier());
            }
        }
    }

    private function validateParents(LineItem $item, CartDataCollection $data, LineItemCollection $items): void
    {
        $blog = $data->get(
            $this->getDataKey((string) $item->getReferencedId())
        );

        // no data for enrich exists
        if (!$blog instanceof ChannelBlogEntity) {
            return;
        }

        // container blogs can not be bought
        if ($blog->getChildCount() <= 0) {
            return;
        }

        $items->remove($item->getId());
    }

    private function validateStock(LineItem $item, Cart $cart, LineItemCollection $scope, CartBehavior $behavior): void
    {
        if ($behavior->hasPermission(self::SKIP_BLOG_STOCK_VALIDATION)) {
            return;
        }

        $minPurchase = 1;
        $steps = 1;
        $available = $item->getQuantity();

        if ($item->getQuantityInformation() !== null) {
            $minPurchase = $item->getQuantityInformation()->getMinPurchase();
            $available = $item->getQuantityInformation()->getMaxPurchase() ?? 0;
            $steps = $item->getQuantityInformation()->getPurchaseSteps() ?? 1;
        }

        if ($available < $minPurchase) {
            $scope->remove($item->getId());

            $cart->addErrors(
                new BlogOutOfStockError((string) $item->getReferencedId(), (string) $item->getLabel())
            );

            return;
        }

        if ($available < $item->getQuantity()) {
            $maxAvailable = $this->fixQuantity($minPurchase, $available, $steps);

            $item->setQuantity($maxAvailable);

            $cart->addErrors(
                new BlogStockReachedError((string) $item->getReferencedId(), (string) $item->getLabel(), $maxAvailable)
            );

            return;
        }

        if ($item->getQuantity() < $minPurchase) {
            $item->setQuantity($minPurchase);

            $cart->addErrors(
                new MinOrderQuantityError((string) $item->getReferencedId(), (string) $item->getLabel(), $minPurchase)
            );

            return;
        }

        $fixedQuantity = $this->fixQuantity($minPurchase, $item->getQuantity(), $steps);
        if ($item->getQuantity() !== $fixedQuantity) {
            $item->setQuantity($fixedQuantity);

            $cart->addErrors(
                new PurchaseStepsError((string) $item->getReferencedId(), (string) $item->getLabel(), $fixedQuantity)
            );
        }
    }

    private function enrich(LineItem $lineItem, CartDataCollection $data, CartBehavior $behavior): void
    {
        $id = $lineItem->getReferencedId();

        $blog = $data->get(
            $this->getDataKey((string) $id)
        );

        // no data for enrich exists
        if (!$blog instanceof ChannelBlogEntity) {
            return;
        }

        $label = trim($lineItem->getLabel() ?? '');

        // set the label if its empty or the context does not have the permission to overwrite it
        if ($label === '' || !$behavior->hasPermission(self::ALLOW_BLOG_LABEL_OVERWRITES)) {
            $lineItem->setLabel($blog->getTranslation('name'));
        }

        $lineItem->setCover($blog->getCover()?->getMedia());

        $deliveryTime = null;
        if ($blog->getDeliveryTime() !== null) {
            $deliveryTime = DeliveryTime::createFromEntity($blog->getDeliveryTime());
        }

        $weight = $blog->getWeight();

        $lineItem->setPayloadValue(LineItem::PAYLOAD_BLOG_TYPE, $blog->getType());

        $isPhysicalLineItem = $lineItem->isBlogType(BlogDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            Feature::callSilentIfInactive('v6.8.0.0', static function () use ($lineItem, $blog, &$isPhysicalLineItem): void {
                $lineItem->setStates($blog->getStates());
                $isPhysicalLineItem = $isPhysicalLineItem || $lineItem->hasState(State::IS_PHYSICAL);
            });
        }

        if ($isPhysicalLineItem) {
            $lineItem->setDeliveryInformation(
                new DeliveryInformation(
                    $blog->getStock(),
                    $weight,
                    $blog->getShippingFree() === true,
                    $blog->getRestockTime(),
                    $deliveryTime,
                    $blog->getHeight(),
                    $blog->getWidth(),
                    $blog->getLength()
                )
            );
        }

        // Check if the price has to be updated
        if ($this->shouldPriceBeRecalculated($lineItem, $behavior)) {
            $lineItem->setPriceDefinition(
                $this->getPriceDefinition($blog, $lineItem->getQuantity())
            );
        }

        $quantityInformation = new QuantityInformation();

        $quantityInformation->setMinPurchase(
            $blog->getMinPurchase() ?? 1
        );

        $quantityInformation->setMaxPurchase(
            $blog->getCalculatedMaxPurchase()
        );

        $quantityInformation->setPurchaseSteps(
            $blog->getPurchaseSteps() ?? 1
        );

        $lineItem->setQuantityInformation($quantityInformation);

        $purchasePrices = null;
        $purchasePricesCollection = $blog->getPurchasePrices();
        if ($purchasePricesCollection !== null) {
            $purchasePrices = $purchasePricesCollection->getCurrencyPrice(Defaults::CURRENCY);
        }

        $payload = [
            'isCloseout' => $blog->getIsCloseout(),
            'customFields' => $blog->getTranslation('customFields'),
            'createdAt' => $blog->getCreatedAt() ? $blog->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'releaseDate' => $blog->getReleaseDate() ? $blog->getReleaseDate()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'isNew' => $blog->isNew(),
            'markAsTopseller' => $blog->getMarkAsTopseller(),
            'purchasePrices' => $purchasePrices ? json_encode($purchasePrices, \JSON_THROW_ON_ERROR) : null,
            'blogNumber' => $blog->getBlogNumber(),
            'manufacturerId' => $blog->getManufacturerId(),
            'taxId' => $blog->getTaxId(),
            'tagIds' => $blog->getTagIds(),
            'categoryIds' => $blog->getCategoryTree(),
            'propertyIds' => $blog->getPropertyIds(),
            'optionIds' => $blog->getOptionIds(),
            'options' => $blog->getVariation(),
            'streamIds' => $blog->getStreamIds(),
            'parentId' => $blog->getParentId(),
            'stock' => $blog->getStock(),
        ];

        $lineItem->replacePayload($payload, ['purchasePrices' => true]);
    }

    private function getPriceDefinition(ChannelBlogEntity $blog, int $quantity): QuantityPriceDefinition
    {
        if ($blog->getCalculatedPrices()->count() === 0) {
            return $this->buildPriceDefinition($blog->getCalculatedPrice(), $quantity);
        }

        $price = $blog->getCalculatedPrice();
        foreach ($blog->getCalculatedPrices() as $calculatedPrice) {
            $price = $calculatedPrice;
            if ($quantity <= $price->getQuantity()) {
                break;
            }
        }

        return $this->buildPriceDefinition($price, $quantity);
    }

    private function buildPriceDefinition(CalculatedPrice $price, int $quantity): QuantityPriceDefinition
    {
        $definition = new QuantityPriceDefinition($price->getUnitPrice(), $price->getTaxRules(), $quantity);
        if ($price->getListPrice() !== null) {
            $definition->setListPrice($price->getListPrice()->getPrice());
        }

        if ($price->getReferencePrice() !== null) {
            $definition->setReferencePriceDefinition(
                new ReferencePriceDefinition(
                    $price->getReferencePrice()->getPurchaseUnit(),
                    $price->getReferencePrice()->getReferenceUnit(),
                    $price->getReferencePrice()->getUnitName()
                )
            );
        }

        if ($price->getRegulationPrice() !== null) {
            $definition->setRegulationPrice($price->getRegulationPrice()->getPrice());
        }

        return $definition;
    }

    /**
     * @param LineItem[] $lineItems
     *
     * @return mixed[]
     */
    private function getNotCompleted(CartDataCollection $data, array $lineItems, string $hash): array
    {
        $ids = [];

        $changes = [];

        foreach ($lineItems as $lineItem) {
            $id = $lineItem->getReferencedId();
            if ($id === '' || $id === null) {
                continue;
            }

            // data already fetched?
            if ($data->has($this->getDataKey($id))) {
                continue;
            }

            // admin_user change line item quantity or price?
            if ($lineItem->isModified()) {
                $ids[] = $id;

                continue;
            }

            if ($lineItem->getDataTimestamp() === null) {
                $ids[] = $id;

                continue;
            }

            if ($lineItem->getDataContextHash() !== $hash) {
                $ids[] = $id;

                continue;
            }

            // check if some data is missing (label, price, cover)
            if (!$this->isComplete($lineItem)) {
                $ids[] = $id;

                continue;
            }

            $changes[$id] = $lineItem->getDataTimestamp()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        if ($changes === []) {
            return $ids;
        }

        $updates = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)) as id, IFNULL(updated_at, created_at) FROM blog WHERE id IN (:ids) AND version_id = :liveVersionId',
            [
                'ids' => Uuid::fromHexToBytesList(array_keys($changes)),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        foreach ($changes as $id => $timestamp) {
            // Blog has been deleted, as we cannot find it
            if (!isset($updates[$id])) {
                $ids[] = $id;

                continue;
            }

            // Blog has been updated, but the timestamp is older than the one we have
            if ($updates[$id] !== $changes[$id]) {
                $ids[] = $id;
            }
        }

        return array_filter(array_unique($ids));
    }

    private function isComplete(LineItem $lineItem): bool
    {
        return $lineItem->getPriceDefinition() !== null
            && $lineItem->getLabel() !== null
            && $lineItem->getDeliveryInformation() !== null
            && $lineItem->getQuantityInformation() !== null;
    }

    private function shouldPriceBeRecalculated(LineItem $lineItem, CartBehavior $behavior): bool
    {
        if ($lineItem->getPriceDefinition() !== null
            && $lineItem->hasExtension(self::CUSTOM_PRICE)
            && $behavior->hasPermission(self::ALLOW_BLOG_PRICE_OVERWRITES)) {
            return false;
        }

        if ($lineItem->getPriceDefinition() !== null
            && $behavior->hasPermission(self::SKIP_BLOG_RECALCULATION)) {
            return false;
        }

        if ($lineItem->getPriceDefinition() !== null && $lineItem->isModifiedByApp()) {
            return false;
        }

        return true;
    }

    private function fixQuantity(int $min, int $current, int $steps): int
    {
        return (int) (floor(($current - $min) / $steps) * $steps + $min);
    }

    private function getDataKey(string $id): string
    {
        return 'blog-' . $id;
    }

    /**
     * @param array<LineItem> $lineItems
     */
    private function recalculate(array $lineItems, CartDataCollection $data, ChannelContext $context, CartBehavior $behavior): void
    {
        $affected = [];

        foreach ($lineItems as $lineItem) {
            if (!$this->shouldPriceBeRecalculated($lineItem, $behavior)) {
                continue;
            }

            $id = $lineItem->getReferencedId();

            $blog = $data->get(
                $this->getDataKey((string) $id)
            );

            // no data for enrich exists
            if (!$blog instanceof ChannelBlogEntity) {
                continue;
            }

            $affected[] = $blog;
        }

        // Check if the price has to be updated
        if ($affected === []) {
            return;
        }

        $this->priceCalculator->calculate($affected, $context);
    }

    private function getDataContextHash(CartDataCollection $data, Cart $original, ChannelContext $context, CartBehavior $behavior): string
    {
        $contextHash = $this->generator->getChannelContextHash($context, [RuleAreas::BLOG_AREA]);

        $activeTaxRules = array_map(static function (TaxEntity $taxRule) {
            return $taxRule->getRules()?->getIds() ?: $taxRule->getId();
        }, $context->getTaxRules()->getElements());

        $event = new BlogCartDataContextHashEvent($data, $original, $context, $behavior);
        $this->eventDispatcher->dispatch($event);

        $hashData = [$contextHash, $activeTaxRules];

        $parts = $event->getParts();
        if ($parts !== []) {
            $hashData[] = $parts;
        }

        return Hasher::hash($hashData);
    }
}
