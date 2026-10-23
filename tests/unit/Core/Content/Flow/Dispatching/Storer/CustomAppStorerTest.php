<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Content\Flow\Dispatching\Storer\CustomAppStorer;
use Contena\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Contena\Core\Framework\App\Event\CustomAppEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CustomAppStorer::class)]
class CustomAppStorerTest extends TestCase
{
    private CustomAppStorer $customAppStorer;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->customAppStorer = new CustomAppStorer();
    }

    public function testStoreWithAwareAndEmptyCustomData(): void
    {
        $event = new CustomAppEvent('custom_app_event', [], $this->context);

        $stored = [];
        $stored = $this->customAppStorer->store($event, $stored);
        static::assertCount(0, $stored);
    }

    public function testStoreWithAware(): void
    {
        $userId = Uuid::randomHex();

        $data = [
            'eventName' => 'swag.before.open_the_doors',
            UserAware::CUSTOMER_ID => $userId,
            ScalarValuesAware::STORE_VALUES => [
                UserAware::CUSTOMER_ID => $userId,
            ],
        ];

        $event = new CustomAppEvent('custom_app_event', $data, $this->context);

        $stored = [];
        $stored = $this->customAppStorer->store($event, $stored);
        static::assertEquals($data, $stored);
    }

    public function testStoreWithScalarAware(): void
    {
        $userId = Uuid::randomHex();

        $data = [
            FlowMailVariables::SHOP_NAME => 'contena',
            FlowMailVariables::RESET_URL => 'http://contena.test',
            UserAware::CUSTOMER_ID => $userId,
        ];

        $expected = [
            UserAware::CUSTOMER_ID => $userId,
            FlowMailVariables::SHOP_NAME => 'contena',
            FlowMailVariables::RESET_URL => 'http://contena.test',
            ScalarValuesAware::STORE_VALUES => [
                FlowMailVariables::SHOP_NAME => 'contena',
                FlowMailVariables::RESET_URL => 'http://contena.test',
                UserAware::CUSTOMER_ID => $userId,
            ],
        ];

        $event = new CustomAppEvent('custom_app_event', $data, $this->context);

        $stored = [];
        $stored = $this->customAppStorer->store($event, $stored);

        static::assertEquals($expected, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = new TestFlowBusinessEvent($this->context);

        $stored = [];
        $stored = $this->customAppStorer->store($event, $stored);
        static::assertCount(0, $stored);
    }
}
