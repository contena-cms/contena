<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Event;

use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductExportChangeEncodingEvent extends Event
{
    final public const NAME = 'product_export.change_encoding';

    public function __construct(
        private readonly ProductExportEntity $productExportEntity,
        private readonly string $content,
        private string $encodedContent
    ) {
    }

    public function getProductExportEntity(): ProductExportEntity
    {
        return $this->productExportEntity;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getEncodedContent(): string
    {
        return $this->encodedContent;
    }

    public function setEncodedContent(string $encodedContent): void
    {
        $this->encodedContent = $encodedContent;
    }
}
