<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Cms\BlogNameCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\TextStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogNameCmsElementResolver::class)]
class BlogNameCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = new BlogNameCmsElementResolver();
        static::assertSame('blog-name', $resolver->getType());
    }

    public function testEnrichSetsEmptyTextStructWithoutConfig(): void
    {
        $slot = new CmsSlotEntity();
        $context = new ResolverContext(Generator::generateChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver = new BlogNameCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $data);
        static::assertNull($data->getContent());
    }

    public function testEnrichStaticContent(): void
    {
        $config = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver = new BlogNameCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $data);
        static::assertSame('my-value', $data->getContent());
    }
}
