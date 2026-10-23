<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\Cms;

use Contena\Core\Content\Blog\Cms\CrossSellingCmsElementResolver;
use Contena\Core\Content\Blog\Cms\BlogDescriptionReviewsCmsElementResolver;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 * $builder = (new LayoutBuilder($ids, $key))
 *     ->blogSlider('slider', $ids->getList(['blog-1', 'blog-2', 'blog-3']));
 *     ->blogThreeColumnBlock('boxes', [
 *         $builder->blogBox('box-1', $ids->get('blog-1')),
 *         $builder->blogBox('box-2', $ids->get('blog-2')),
 *         $builder->blogBox('box-3', $ids->get('blog-3'))
 *     ]);
 */
class LayoutBuilder
{
    protected string $id;

    protected ?string $name;

    /**
     * @var mixed[]
     */
    protected array $_dynamic = [];

    /**
     * @var mixed[]
     */
    protected array $blocks;

    /**
     * @var mixed[]
     */
    protected array $sections = [];

    public function __construct(
        protected IdsCollection $ids,
        string $key,
        protected string $type = 'landingpage'
    ) {
        $this->id = $this->ids->create($key);
        $this->name = $key;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $data = get_object_vars($this);

        unset($data['ids'], $data['_dynamic']);

        $data = array_merge($data, $this->_dynamic);

        $data['sections'] = array_values($data['sections']);

        return array_filter($data);
    }

    public function listing(string $section = 'main'): LayoutBuilder
    {
        $this->section($section);

        $this->sections[$section]['blocks'][] = array_merge([
            'position' => $this->blockPosition($section),
            'type' => 'blog-listing',
            'sectionPosition' => 'main',
            'slots' => [
                ['type' => 'blog-listing', 'slot' => 'content', 'config' => []],
            ],
        ], self::margin(20, 20, 20, 20));

        return $this;
    }

    public function image(string $key, string $section = 'main'): self
    {
        $this->section($section);

        $this->sections[$section]['blocks'][] = array_merge(
            [
                'type' => 'image',
                'position' => $this->blockPosition($section),
                'sectionPosition' => 'main',
                'backgroundMediaMode' => 'cover',
                'slots' => [
                    [
                        'type' => 'image',
                        'slot' => 'image',
                        'config' => [
                            'url' => ['source' => 'static', 'value' => 300],
                            'media' => ['source' => 'static', 'value' => $this->ids->get($key)],
                            'newTab' => ['source' => 'static', 'value' => false],
                            'minHeight' => ['source' => 'static', 'value' => '340px'],
                            'displayMode' => ['source' => 'static', 'value' => 'standard'],
                            'verticalAlign' => ['source' => 'static', 'value' => null],
                        ],
                    ],
                ],
            ],
            self::margin(20, 20, 20, 20)
        );

        return $this;
    }

    /**
     * @param list<string> $keys
     */
    public function imageSlider(array $keys, string $section = 'main'): self
    {
        $this->section($section);

        $this->sections[$section]['blocks'][] = array_merge(
            [
                'type' => 'image-slider',
                'position' => $this->blockPosition($section),
                'sectionPosition' => 'main',
                'backgroundMediaMode' => 'cover',
                'slots' => [
                    [
                        'type' => 'image-slider',
                        'slot' => 'imageSlider',
                        'config' => [
                            'sliderItems' => [
                                'source' => 'static',
                                'value' => array_map(static fn (string $id) => ['mediaId' => $id], array_values($this->ids->getList($keys))),
                            ],
                            'speed' => ['source' => 'static', 'value' => 300],
                            'autoSlide' => ['source' => 'static', 'value' => false],
                            'minHeight' => ['source' => 'static', 'value' => '300px'],
                            'displayMode' => ['source' => 'static', 'value' => 'standard'],
                            'verticalAlign' => ['source' => 'static', 'value' => null],
                            'navigationDots' => ['source' => 'static', 'value' => true],
                            'autoplayTimeout' => ['source' => 'static', 'value' => 5000],
                            'navigationArrows' => ['source' => 'static', 'value' => 'outside'],
                        ],
                    ],
                ],
            ],
            self::margin(20, 20, 20, 20)
        );

        return $this;
    }

    /**
     * @param list<string> $keys
     */
    public function imageGallery(array $keys, string $section = 'main'): self
    {
        $this->section($section);

        $this->sections[$section]['blocks'][] = array_merge(
            [
                'type' => 'image-gallery',
                'position' => $this->blockPosition($section),
                'sectionPosition' => 'main',
                'backgroundMediaMode' => 'cover',
                'slots' => [
                    [
                        'type' => 'image-gallery',
                        'slot' => 'imageGallery',
                        'config' => [
                            'sliderItems' => [
                                'source' => 'static',
                                'value' => array_map(static fn (string $id) => ['mediaId' => $id], array_values($this->ids->getList($keys))),
                            ],
                            'speed' => ['source' => 'static', 'value' => 300],
                            'autoSlide' => ['source' => 'static', 'value' => false],
                            'minHeight' => ['source' => 'static', 'value' => '300px'],
                            'displayMode' => ['source' => 'static', 'value' => 'standard'],
                            'verticalAlign' => ['source' => 'static', 'value' => null],
                            'navigationDots' => ['source' => 'static', 'value' => true],
                            'autoplayTimeout' => ['source' => 'static', 'value' => 5000],
                            'navigationArrows' => ['source' => 'static', 'value' => 'outside'],
                        ],
                    ],
                ],
            ],
            self::margin(20, 20, 20, 20)
        );

        return $this;
    }

    /**
     * @return array{
     *     type: string,
     *     config: array{
     *         blog: array{source: string, value: string},
     *         boxLayout: array{source: string, value: string},
     *         displayMode: array{source: string, value: string},
     *         verticalAlign: array{source: string, value: null}
     *     }
     * }
     */
    public function blogBox(string $key, string $boxLayout = 'standard', string $displayMode = 'standard'): array
    {
        return [
            'type' => 'blog-box',
            'config' => [
                'blog' => ['source' => 'static', 'value' => $this->ids->get($key)],
                'boxLayout' => ['source' => 'static', 'value' => $boxLayout],
                'displayMode' => ['source' => 'static', 'value' => $displayMode],
                'verticalAlign' => ['source' => 'static', 'value' => null],
            ],
        ];
    }

    public function section(string $key): void
    {
        if (isset($this->sections[$key])) {
            return;
        }

        $this->sections[$key] = [
            'type' => 'default',
            'position' => \count($this->sections),
            'blocks' => [],
        ];
    }

    public function blogHeading(?string $key = null, string $section = 'main'): self
    {
        $this->section($section);
        $key ??= Uuid::randomHex();

        $this->sections[$section]['blocks'][$key] = array_merge(
            [
                'type' => 'blog-heading',
                'position' => $this->blockPosition($section),
                'slots' => [
                    ['type' => 'blog-name', 'slot' => 'left'],
                    ['type' => 'manufacturer-logo', 'slot' => 'right'],
                ],
            ],
            self::margin(0, 0, 20, 0)
        );

        return $this;
    }

    public function galleryBuybox(?string $key = null, string $section = 'main'): self
    {
        $this->section($section);
        $key ??= Uuid::randomHex();
        $this->sections[$section]['blocks'][$key] = array_merge(
            [
                'type' => 'gallery-buybox',
                'position' => $this->blockPosition($section),
                'slots' => [
                    ['type' => 'image-gallery', 'slot' => 'left'],
                    ['type' => 'buy-box', 'slot' => 'right'],
                ],
            ],
            self::margin(20, 0, 0, 0)
        );

        return $this;
    }

    public function descriptionReviews(?string $key = null, string $section = 'main'): self
    {
        $this->section($section);
        $key ??= Uuid::randomHex();
        $this->sections[$section]['blocks'][$key] = array_merge(
            [
                'type' => BlogDescriptionReviewsCmsElementResolver::TYPE,
                'position' => $this->blockPosition($section),
                'slots' => [
                    ['type' => BlogDescriptionReviewsCmsElementResolver::TYPE, 'slot' => 'content'],
                ],
            ],
            self::margin(20, 0, 20, 0)
        );

        return $this;
    }

    public function crossSelling(?string $key = null, string $section = 'main'): self
    {
        $this->section($section);
        $key ??= Uuid::randomHex();
        $this->sections[$section]['blocks'][$key] = array_merge(
            [
                'type' => CrossSellingCmsElementResolver::TYPE,
                'position' => $this->blockPosition($section),
                'slots' => [
                    ['type' => CrossSellingCmsElementResolver::TYPE, 'slot' => 'content'],
                ],
            ],
            self::margin(20, 0, 20, 0)
        );

        return $this;
    }

    private function blockPosition(string $section): int
    {
        return is_countable($this->sections[$section]['blocks']) ? \count($this->sections[$section]['blocks']) : 0;
    }

    /**
     * @return array{marginTop: string, marginRight: string, marginBottom: string, marginLeft: string}
     */
    private static function margin(int $top, int $right, int $bottom, int $left): array
    {
        return [
            'marginTop' => $top > 0 ? $top . 'px' : '0',
            'marginRight' => $right > 0 ? $right . 'px' : '0',
            'marginBottom' => $bottom > 0 ? $bottom . 'px' : '0',
            'marginLeft' => $left > 0 ? $top . '$left' : '0',
        ];
    }
}
