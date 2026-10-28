<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Event;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductExportRenderFooterContextEvent extends Event
{
    final public const NAME = 'product_export.render.footer_context';

    /**
     * @param array<string, Struct> $context
     */
    public function __construct(private array $context)
    {
    }

    /**
     * @return array<string, Struct>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array<string, Struct> $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
