<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Region\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class RegionRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->ids->set('countryId', $this->getCountryIdByIsoCode('CN'));

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
            'countryId' => $this->ids->get('countryId'),
            'countries' => [['id' => $this->ids->get('countryId')]],
        ]);
    }

    public function testGetRegions(): void
    {
        $this->request();

        $response = $this->response();

        static::assertCount(34, $response['elements']);
        static::assertContains($this->ids->get('countryId'), array_column($response['elements'], 'countryId'));
        static::assertContains('11', array_column($response['elements'], 'code'));
    }

    public function testIncludes(): void
    {
        $this->request([
            'includes' => [
                'region' => ['code'],
            ],
        ]);

        $response = $this->response();

        static::assertCount(34, $response['elements']);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
        static::assertContains('11', array_column($response['elements'], 'code'));
    }

    public function testLimit(): void
    {
        $this->request(['limit' => 2]);

        static::assertCount(2, $this->response()['elements']);
    }

    public function testSortByPosition(): void
    {
        $this->request(['limit' => 2]);

        $response = $this->response();

        static::assertNotNull($response['elements']);
        static::assertCount(2, $response['elements']);
        static::assertSame(['11', '12'], array_column($response['elements'], 'code'));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function request(array $parameters = []): void
    {
        $this->browser->request(
            'POST',
            '/channel-api/region/' . $this->ids->get('countryId'),
            $parameters,
        );
    }

    /**
     * @return array{elements: list<array<string, mixed>>}
     */
    private function response(): array
    {
        /** @var array{elements: list<array<string, mixed>>} $response */
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $response;
    }
}
