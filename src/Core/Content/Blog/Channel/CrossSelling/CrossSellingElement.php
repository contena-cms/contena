<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\CrossSelling;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

class CrossSellingElement extends Struct
{

    protected BlogCollection $blogs;

    protected int $total;

    protected ?string $streamId = null;

    public function getCrossSelling(): BlogCrossSellingEntity
    {
        return $this->crossSelling;
    }

    public function setCrossSelling(BlogCrossSellingEntity $crossSelling): void
    {
        $this->crossSelling = $crossSelling;
    }

    public function getBlogs(): BlogCollection
    {
        return $this->blogs;
    }

    public function setBlogs(BlogCollection $blogs): void
    {
        $this->blogs = $blogs;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function getApiAlias(): string
    {
        return 'cross_selling_element';
    }

    public function getStreamId(): ?string
    {
        return $this->streamId;
    }

    public function setStreamId(?string $streamId): void
    {
        $this->streamId = $streamId;
    }
}
