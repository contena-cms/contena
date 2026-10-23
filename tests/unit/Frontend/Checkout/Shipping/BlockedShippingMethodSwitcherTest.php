<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Checkout\Shipping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Shipping\Channel\ShippingMethodRoute;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Checkout\Cart\Error\ShippingMethodChangedError;
use Contena\Frontend\Checkout\Shipping\BlockedShippingMethodSwitcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlockedShippingMethodSwitcher::class)]
class BlockedShippingMethodSwitcherTest extends TestCase
{

    private ChannelContext $channelContext;


    protected function setUp(): void
    {
        $this->shippingMethodCollection = new ShippingMethodCollection([
        ]);

        $this->channelContext = $this->getChannelContext();
        $this->switcher = new BlockedShippingMethodSwitcher(
            $this->getShippingMethodRoute()
        );
    }

    public function testSwitchDoesNotSwitchWithNoErrors(): void
    {
        $errorCollection = $this->getErrorCollection();
        $newShippingMethod = $this->switcher->switch($errorCollection, $this->channelContext);

        static::assertSame('original-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            static fn ($error) => $error instanceof ShippingMethodChangedError
        );

        static::assertCount(0, $errorCollectionFiltered);
    }

    public function testSwitchBlockedOriginalSwitchToDefault(): void
    {
        $errorCollection = $this->getErrorCollection([
            ['id' => 'original-shipping-method-id', 'name' => 'original-shipping-method-name'],
        ]);
        $newShippingMethod = $this->switcher->switch($errorCollection, $this->channelContext);

        static::assertSame('default-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            static fn ($error) => $error instanceof ShippingMethodChangedError
        );
        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'oldShippingMethodId' => 'original-shipping-method-id',
            'oldShippingMethodName' => 'original-shipping-method-name',
            'newShippingMethodId' => 'default-shipping-method-id',
            'newShippingMethodName' => 'default-shipping-method-name',
            'reason' => 'Shipping method blocked',
        ], $error->getParameters());
    }

    public function testSwitchBlockedOriginalWithTranslatedName(): void
    {
        $errorCollection = $this->getErrorCollection([
            ['id' => 'original-shipping-method-id', 'name' => 'original-shipping-method-name'],
        ]);

        $this->shippingMethodCollection->remove('any-other-shipping-method-id');
        $this->shippingMethodCollection->remove('default-shipping-method-id');
        $this->shippingMethodCollection->add((new ShippingMethodEntity())->assign([
            'id' => 'translated-shipping-method-id',
            'name' => null,
            'translated' => ['name' => 'translated-shipping-method-name'],
        ]));

        $newPaymentMethod = $this->switcher->switch($errorCollection, $this->channelContext);
        static::assertSame('translated-shipping-method-id', $newPaymentMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            static fn ($error) => $error instanceof ShippingMethodChangedError
        );
        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'oldShippingMethodId' => 'original-shipping-method-id',
            'oldShippingMethodName' => 'original-shipping-method-name',
            'newShippingMethodId' => 'translated-shipping-method-id',
            'newShippingMethodName' => 'translated-shipping-method-name',
            'reason' => 'Shipping method blocked',
        ], $error->getParameters());
    }

    public function testSwitchBlockedOriginalAndDefaultSwitchToAnyOther(): void
    {
        $errorCollection = $this->getErrorCollection([
            ['id' => 'original-shipping-method-id', 'name' => 'original-shipping-method-name'],
            ['id' => 'default-shipping-method-id', 'name' => 'default-shipping-method-name'],
        ]);
        $newShippingMethod = $this->switcher->switch($errorCollection, $this->channelContext);

        static::assertSame('any-other-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            static fn ($error) => $error instanceof ShippingMethodChangedError
        );
        static::assertCount(2, $errorCollectionFiltered);

        $expectedParameters = [
            [
                'oldShippingMethodId' => 'original-shipping-method-id',
                'oldShippingMethodName' => 'original-shipping-method-name',
                'newShippingMethodId' => 'any-other-shipping-method-id',
                'newShippingMethodName' => 'any-other-shipping-method-name',
                'reason' => 'Shipping method blocked',
            ],
            [
                'oldShippingMethodId' => 'default-shipping-method-id',
                'oldShippingMethodName' => 'default-shipping-method-name',
                'newShippingMethodId' => 'any-other-shipping-method-id',
                'newShippingMethodName' => 'any-other-shipping-method-name',
                'reason' => 'Shipping method blocked',
            ],
        ];

        $i = 0;
        foreach ($errorCollectionFiltered as $error) {
            static::assertSame($error->getParameters(), $expectedParameters[$i]);
            ++$i;
        }
    }

    public function testSwitchBlockedOriginalAndNoDefaultSwitchToAnyOther(): void
    {
        $errorCollection = $this->getErrorCollection([
            ['id' => 'original-shipping-method-id', 'name' => 'original-shipping-method-name'],
        ]);
        $channelContext = $this->getChannelContext(true);
        $newShippingMethod = $this->switcher->switch($errorCollection, $channelContext);

        static::assertSame('any-other-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            static fn ($error) => $error instanceof ShippingMethodChangedError
        );

        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'oldShippingMethodId' => 'original-shipping-method-id',
            'oldShippingMethodName' => 'original-shipping-method-name',
            'newShippingMethodId' => 'any-other-shipping-method-id',
            'newShippingMethodName' => 'any-other-shipping-method-name',
            'reason' => 'Shipping method blocked',
        ], $error->getParameters());
    }

    public function testSwitchWithProvidedShippingMethodsDoesNotLoadRoute(): void
    {
        $errorCollection = $this->getErrorCollection([
            ['id' => 'original-shipping-method-id', 'name' => 'original-shipping-method-name'],
        ]);

        $shippingMethodRoute = $this->createMock(ShippingMethodRoute::class);
        $shippingMethodRoute
            ->expects($this->never())
            ->method('load');

        $switcher = new BlockedShippingMethodSwitcher($shippingMethodRoute);
        $anyOtherShippingMethod = $this->shippingMethodCollection->get('any-other-shipping-method-id');
        $defaultShippingMethod = $this->shippingMethodCollection->get('default-shipping-method-id');
        static::assertInstanceOf(ShippingMethodEntity::class, $anyOtherShippingMethod);
        static::assertInstanceOf(ShippingMethodEntity::class, $defaultShippingMethod);

        $newShippingMethod = $switcher->switch(
            $errorCollection,
            $this->channelContext,
            new ShippingMethodCollection([
                $anyOtherShippingMethod,
                $defaultShippingMethod,
            ])
        );

        static::assertSame('default-shipping-method-id', $newShippingMethod->getId());

        $errorCollectionFiltered = $errorCollection->filter(
            static fn ($error) => $error instanceof ShippingMethodChangedError
        );
        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'oldShippingMethodId' => 'original-shipping-method-id',
            'oldShippingMethodName' => 'original-shipping-method-name',
            'newShippingMethodId' => 'default-shipping-method-id',
            'newShippingMethodName' => 'default-shipping-method-name',
            'reason' => 'Shipping method blocked',
        ], $error->getParameters());
    }

    public function testSwitchBlockedOriginalAndDefaultAndAnyOtherDoesNotSwitch(): void
    {
        $switcher = new BlockedShippingMethodSwitcher(
            $this->getShippingMethodRoute(true)
        );
        $errorCollection = $this->getErrorCollection([
            ['id' => 'original-shipping-method-id', 'name' => 'original-shipping-method-name'],
            ['id' => 'default-shipping-method-id', 'name' => 'default-shipping-method-name'],
        ]);
        $newShippingMethod = $switcher->switch($errorCollection, $this->channelContext);

        static::assertSame('original-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            static fn ($error) => $error instanceof ShippingMethodChangedError
        );

        static::assertCount(0, $errorCollectionFiltered);
    }



    public function testOnlyAvailableFlagIsSet(): void
    {
        $shippingMethod = $this->shippingMethodCollection->get('original-shipping-method-id');
        $errors = $this->getErrorCollection([
            ['id' => 'original-shipping-method-id', 'name' => 'original-shipping-method-name'],
        ]);

        $context = Generator::generateChannelContext(shippingMethod: $shippingMethod);

        $shippingMethodRoute = $this->createMock(ShippingMethodRoute::class);
        $shippingMethodRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::equalTo(new Request(['onlyAvailable' => true])),
                $context,
                static::isInstanceOf(Criteria::class)
            );

        $switcher = new BlockedShippingMethodSwitcher($shippingMethodRoute);
        $switcher->switch($errors, $context);
    }


    private function getChannelContext(bool $dontReturnDefaultShippingMethod = false): ChannelContext
    {
        $channel = new ChannelEntity();
        $channel->setId(TestDefaults::CHANNEL);
        $channel->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        if ($dontReturnDefaultShippingMethod) {
            $channel->setShippingMethodId('not-a-valid-id');
        } else {
            $channel->setShippingMethodId('default-shipping-method-id');
        }

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannel')->willReturn($channel);
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $channelContext->method('getShippingMethod')->willReturn($this->shippingMethodCollection->get('original-shipping-method-id'));

        return $channelContext;
    }

}
