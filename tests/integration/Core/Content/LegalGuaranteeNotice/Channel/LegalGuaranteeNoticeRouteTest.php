<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\LegalGuaranteeNotice\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Group('store-api')]
class LegalGuaranteeNoticeRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private const GERMAN_LANGUAGE_ID = '20354d7ae4fe47af8ff6187bc0dedede';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        static::getContainer()->get('language.repository')->create([[
            'id' => self::GERMAN_LANGUAGE_ID,
            'name' => 'TestGerman',
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'active' => true,
            'locale' => [
                'id' => $this->ids->create('locale-de'),
                'name' => 'TestGerman',
                'territory' => 'TestGermany',
                'code' => 'de-DE-test',
            ],
            'translationCodeId' => $this->ids->get('locale-de'),
        ]], Context::createDefaultContext());
    }

    public function testNoticeIsRenderedInGermanForGermanChannel(): void
    {
        $browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'languageId' => self::GERMAN_LANGUAGE_ID,
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => self::GERMAN_LANGUAGE_ID],
            ],
        ]);

        static::getContainer()->get(SystemConfigService::class)->set('core.cart.showLegalGuaranteeNotice', true);

        $browser->request('GET', '/store-api/legal-guarantee-notice');

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsString($response['svg']);
        static::assertSame('https://europa.eu/youreurope/garantien', $response['link']);
    }

    public function testNoticeIsNullWhenToggleIsDisabled(): void
    {
        $browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        static::getContainer()->get(SystemConfigService::class)->set('core.cart.showLegalGuaranteeNotice', false);

        $browser->request('GET', '/store-api/legal-guarantee-notice');

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['svg']);
        static::assertNull($response['link']);
    }

    public function testNoticeCanBeDisabledPerChannel(): void
    {
        $channelId = $this->ids->create('sales-channel');
        $browser = $this->createCustomChannelBrowser(['id' => $channelId]);

        static::getContainer()->get(SystemConfigService::class)->set('core.cart.showLegalGuaranteeNotice', true);
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.cart.showLegalGuaranteeNotice', false, $channelId);

        $browser->request('GET', '/store-api/legal-guarantee-notice');

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['svg']);
    }
}
