<?php declare(strict_types=1);

namespace Contena\Tests\Bench\Core;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Content\ProductExport\Service\ProductExportRenderer;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Bench\AbstractBenchCase;

/**
 * @internal - only for performance benchmarks
 */
class ProductExportRendererBench extends AbstractBenchCase
{
    private ProductExportRenderer $renderer;

    private ProductExportEntity $productExport;

    private ProductEntity $product;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    #[BeforeMethods(['setUpScalarContextBenchmark'])]
    #[AfterMethods(['tearDown'])]
    public function bench_render_body_with_scalar_context(): void
    {
        $this->renderer->renderBody($this->productExport, $this->context, $this->data);
    }

    #[BeforeMethods(['setUpWithCompliantMediaUrlBenchmark'])]
    #[AfterMethods(['tearDown'])]
    public function bench_render_body_with_compliant_media_url(): void
    {
        $this->renderer->renderBody($this->productExport, $this->context, $this->data);
    }

    #[BeforeMethods(['setUpWithUnencodedMediaUrlBenchmark'])]
    #[AfterMethods(['tearDown'])]
    public function bench_render_body_with_unencoded_media_url(): void
    {
        $this->renderer->renderBody($this->productExport, $this->context, $this->data);
    }

    public function setUpScalarContextBenchmark(): void
    {
        $this->setUpRendererBenchmark();
        $this->productExport->setBodyTemplate('{{ name }}');
        $this->data = ['name' => 'Simple product'];
    }

    public function setUpWithCompliantMediaUrlBenchmark(): void
    {
        $this->setUpBenchmark('https://example.com/media/Product%20image.jpg');
    }

    public function setUpWithUnencodedMediaUrlBenchmark(): void
    {
        $this->setUpBenchmark('https://example.com/media/Product image.jpg');
    }

    private function setUpBenchmark(string $mediaUrl): void
    {
        $this->setUpRendererBenchmark();
        $this->productExport->setBodyTemplate('{{ product.name }}');

        $this->product = new ProductEntity();
        $this->product->setId(Uuid::randomHex());
        $this->product->setName('Simple product');

        $media = new MediaEntity();
        $media->setUrl($mediaUrl);

        $cover = new ProductMediaEntity();
        $cover->setMedia($media);

        $this->product->setCover($cover);
        $this->product->addExtension('benchmark', new ArrayStruct([
            'nodes' => array_map(
                static fn (int $index): ArrayStruct => new ArrayStruct([
                    'identifier' => 'node-' . $index,
                    'metadata' => new ArrayStruct([
                        'label' => 'Benchmark node ' . $index,
                        'value' => $index,
                    ]),
                ]),
                range(1, 100)
            ),
        ]));

        $this->data = ['product' => $this->product];
    }

    private function setUpRendererBenchmark(): void
    {
        parent::setUp();

        $this->renderer = static::getContainer()->get(ProductExportRenderer::class);
        $this->productExport = new ProductExportEntity();
    }
}
