<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Product;

use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Page;

#[Package('inventory')]
class ProductPage extends Page
{
    protected SalesChannelProductEntity $product;


    protected ?string $navigationId = null;

    protected PropertyGroupCollection $configuratorSettings;

    protected PropertyGroupOptionCollection $selectedOptions;


    protected ?BreadcrumbCollection $breadcrumb = null;

    public function getProduct(): SalesChannelProductEntity
    {
        return $this->product;
    }

    public function setProduct(SalesChannelProductEntity $product): void
    {
        $this->product = $product;
    }



    public function getNavigationId(): ?string
    {
        return $this->navigationId;
    }

    public function setNavigationId(?string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }

    public function getConfiguratorSettings(): PropertyGroupCollection
    {
        return $this->configuratorSettings;
    }

    public function setConfiguratorSettings(PropertyGroupCollection $configuratorSettings): void
    {
        $this->configuratorSettings = $configuratorSettings;
    }

    public function getSelectedOptions(): PropertyGroupOptionCollection
    {
        return $this->selectedOptions;
    }

    public function setSelectedOptions(PropertyGroupOptionCollection $selectedOptions): void
    {
        $this->selectedOptions = $selectedOptions;
    }



    public function getBreadcrumb(): ?BreadcrumbCollection
    {
        return $this->breadcrumb;
    }

    public function setBreadcrumb(?BreadcrumbCollection $breadcrumb): void
    {
        $this->breadcrumb = $breadcrumb;
    }

    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }
}
