<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\System\Salutation\AbstractSalutationsSorter;
use Contena\Core\System\Salutation\Channel\AbstractSalutationRoute;
use Contena\Core\System\Salutation\SalutationCollection;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FormCmsElementResolver::class)]
class FormCmsElementResolverTest extends TestCase
{
    public function testType(): void
    {
        $formCmsElementResolver = new FormCmsElementResolver(
            static::createStub(AbstractSalutationRoute::class),
            static::createStub(AbstractSalutationsSorter::class)
        );

        static::assertSame('form', $formCmsElementResolver->getType());
    }

    public function testResolverUsesAbstractSalutationsRouteToEnrichSlot(): void
    {
        $salutationCollection = $this->getSalutationCollection();
        $sorter = static::createStub(AbstractSalutationsSorter::class);
        $sorter->method('sort')->willReturnArgument(0);
        $formCmsElementResolver = new FormCmsElementResolver($this->getSalutationRoute($salutationCollection), $sorter);

        $formElement = $this->getCmsFormElement();
        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $formCmsElementResolver->enrich(
            $formElement,
            $context,
            new ElementDataCollection()
        );

        static::assertSame($formElement->getData(), $salutationCollection);
    }

    public function testResolverDelegatesToSalutationsSorter(): void
    {
        $salutationCollection = $this->getSalutationCollection();
        $sortedCollection = new SalutationCollection();

        $sorter = $this->createMock(AbstractSalutationsSorter::class);
        $sorter->expects($this->once())
            ->method('sort')
            ->with($salutationCollection)
            ->willReturn($sortedCollection);

        $formCmsElementResolver = new FormCmsElementResolver($this->getSalutationRoute($salutationCollection), $sorter);

        $formElement = $this->getCmsFormElement();
        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $formCmsElementResolver->enrich(
            $formElement,
            $context,
            new ElementDataCollection()
        );

        static::assertSame($sortedCollection, $formElement->getData());
    }

    public function testCollectReturnsNull(): void
    {
        $context = new ResolverContext(Generator::generateChannelContext(), new Request());
        $salutationRoute = static::createStub(AbstractSalutationRoute::class);

        $formCmsElementResolver = new FormCmsElementResolver(
            $salutationRoute,
            static::createStub(AbstractSalutationsSorter::class)
        );
        $actual = $formCmsElementResolver->collect(new CmsSlotEntity(), $context);

        static::assertNull($actual);
    }

    private function getCmsFormElement(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setType('form');
        $slot->setUniqueIdentifier('id');

        return $slot;
    }



}
