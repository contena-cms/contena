<?php declare(strict_types=1);

namespace Contena\Core\System\Tag;

use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class TagEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected ?ProductCollection $products = null;

    protected ?MediaCollection $media = null;

    protected ?CategoryCollection $categories = null;

    protected ?CustomerCollection $customers = null;





    protected ?RuleCollection $rules = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getMedia(): ?MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getCategories(): ?CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }









    public function getRules(): ?RuleCollection
    {
        return $this->rules;
    }

    public function setRules(RuleCollection $rules): void
    {
        $this->rules = $rules;
    }
}
