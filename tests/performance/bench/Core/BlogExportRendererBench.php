<?php declare(strict_types=1);

namespace Contena\Tests\Bench\Core;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\BlogExport\BlogExportEntity;
use Contena\Core\Content\BlogExport\Service\BlogExportRenderer;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal - only for performance benchmarks
 */
class BlogExportRendererBench extends AbstractBenchCase
{
    private BlogExportRenderer $renderer;

    private BlogExportEntity $blogExport;

    private BlogEntity $blog;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    #[BeforeMethods(['setUpScalarContextBenchmark'])]
    #[AfterMethods(['tearDown'])]
    public function bench_render_body_with_scalar_context(): void
    {
        $this->renderer->renderBody($this->blogExport, $this->context, $this->data);
    }

    #[BeforeMethods(['setUpWithCompliantMediaUrlBenchmark'])]
    #[AfterMethods(['tearDown'])]
    public function bench_render_body_with_compliant_media_url(): void
    {
        $this->renderer->renderBody($this->blogExport, $this->context, $this->data);
    }

    #[BeforeMethods(['setUpWithUnencodedMediaUrlBenchmark'])]
    #[AfterMethods(['tearDown'])]
    public function bench_render_body_with_unencoded_media_url(): void
    {
        $this->renderer->renderBody($this->blogExport, $this->context, $this->data);
    }

    public function setUpScalarContextBenchmark(): void
    {
        $this->setUpRendererBenchmark();
        $this->blogExport->setBodyTemplate('{{ name }}');
        $this->data = ['name' => 'Simple blog'];
    }

    public function setUpWithCompliantMediaUrlBenchmark(): void
    {
        $this->setUpBenchmark('https://example.com/media/Blog%20image.jpg');
    }

    public function setUpWithUnencodedMediaUrlBenchmark(): void
    {
        $this->setUpBenchmark('https://example.com/media/Blog image.jpg');
    }

    private function setUpBenchmark(string $mediaUrl): void
    {
        $this->setUpRendererBenchmark();
        $this->blogExport->setBodyTemplate('{{ blog.name }}');

        $this->blog = new BlogEntity();
        $this->blog->setId(Uuid::randomHex());
        $this->blog->setName('Simple blog');

        $media = new MediaEntity();
        $media->setUrl($mediaUrl);

        $cover = new BlogMediaEntity();
        $cover->setMedia($media);

        $this->blog->setCover($cover);
        $this->blog->addExtension('benchmark', new ArrayStruct([
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

        $this->data = ['blog' => $this->blog];
    }

    private function setUpRendererBenchmark(): void
    {
        parent::setUp();

        $this->renderer = static::getContainer()->get(BlogExportRenderer::class);
        $this->blogExport = new BlogExportEntity();
    }
}
