<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Currency\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class CurrencyRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'currencyId' => $this->ids->get('currency'),
            'currencies' => [
                ['id' => $this->ids->get('currency')],
                ['id' => $this->ids->get('currency2')],
            ],
        ]);
    }

    public function testCurrencies(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/currency',
                [
                ]
            );

        $response = $this->getResponse();

        static::assertCount(2, $response);
        static::assertContains($this->ids->get('currency'), array_column($response, 'id'));
        static::assertContains('FO', array_column($response, 'isoCode'));
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/currency',
                [
                    'includes' => [
                        'currency' => ['isoCode'],
                    ],
                ]
            );

        $response = $this->getResponse();

        static::assertCount(2, $response);
        static::assertArrayNotHasKey('id', $response[0]);
        static::assertContains('te', array_column($response, 'isoCode'));
    }

    public function testLimit(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/currency',
                [
                    'limit' => 1,
                ]
            );

        $response = $this->getResponse();

        static::assertCount(1, $response);
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('currency'),
                'decimalPrecision' => 2,
                'name' => 'match',
                'isoCode' => 'FO',
                'shortName' => 'test',
                'factor' => 1,
                'symbol' => 'A',
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            ],
            [
                'id' => $this->ids->create('currency2'),
                'decimalPrecision' => 2,
                'name' => 'match2',
                'isoCode' => 'te',
                'shortName' => 'yay',
                'factor' => 1,
                'symbol' => 'B',
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            ],
        ];

        static::getContainer()->get('currency.repository')
            ->create($data, Context::createDefaultContext());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getResponse(): array
    {
        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
