<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel\Garan;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Group('store-api')]
class GaranLabelRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testGaranLabelIsRenderedForCompleteBlog(): void
    {
        $blog = (new BlogBuilder($this->ids, 'garan-blog'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->manufacturer('acme')
            ->build();
        $blog['manufacturerNumber'] = 'ACME-123';
        $blog['guaranteeMonths'] = 36;
        $blog['guaranteeConfirmed'] = true;

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        $this->browser->request('GET', '/store-api/blog/' . $this->ids->get('garan-blog') . '/garan-label');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsString($response['svg']);
        static::assertStringContainsString('ACME-123', $response['svg']);
        static::assertStringContainsString('3', $response['svg']);

        static::assertIsString($response['nestedSvg']);
        static::assertStringContainsString('3', $response['nestedSvg']);
    }

    public function testGaranLabelIsNullWhenGuaranteeConfirmedIsFalse(): void
    {
        $blog = (new BlogBuilder($this->ids, 'garan-blog-unconfirmed'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->manufacturer('acme')
            ->build();
        $blog['manufacturerNumber'] = 'ACME-123';
        $blog['guaranteeMonths'] = 36;
        $blog['guaranteeConfirmed'] = false;

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        $this->browser->request('GET', '/store-api/blog/' . $this->ids->get('garan-blog-unconfirmed') . '/garan-label');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['svg']);
        static::assertNull($response['nestedSvg']);
    }

    public function testGaranLabelIsNullWhenManufacturerNumberIsMissingEvenWithBlogNumber(): void
    {
        $blog = (new BlogBuilder($this->ids, 'garan-blog-no-manufacturer-number'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->manufacturer('acme')
            ->build();
        $blog['guaranteeMonths'] = 36;
        $blog['guaranteeConfirmed'] = true;

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        $this->browser->request('GET', '/store-api/blog/' . $this->ids->get('garan-blog-no-manufacturer-number') . '/garan-label');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['svg']);
        static::assertNull($response['nestedSvg']);
    }

    public function testGaranLabelIsNullWhenGuaranteeMonthsIsMissing(): void
    {
        $blog = (new BlogBuilder($this->ids, 'garan-blog-no-guarantee'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->manufacturer('acme')
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        $this->browser->request('GET', '/store-api/blog/' . $this->ids->get('garan-blog-no-guarantee') . '/garan-label');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['svg']);
        static::assertNull($response['nestedSvg']);
    }

    public function testGaranLabelRouteReturnsNotFoundForUnknownBlog(): void
    {
        $this->browser->request('GET', '/store-api/blog/' . $this->ids->create('unknown-blog') . '/garan-label');

        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
    }

    public function testGaranLabelIsRenderedForLanguageWithoutOwnManufacturerTranslation(): void
    {
        $context = Context::createDefaultContext();

        $localeCriteria = new Criteria();
        $localeCriteria->addFilter(new EqualsFilter('code', 'vi-VN'));
        $localeId = static::getContainer()->get('locale.repository')->searchIds($localeCriteria, $context)->firstId();
        static::assertIsString($localeId);

        static::getContainer()->get('language.repository')->create([[
            'id' => $this->ids->get('language'),
            'name' => 'TestLanguage',
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'active' => true,
            'localeId' => $localeId,
            'translationCodeId' => $localeId,
        ]], $context);

        $browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->get('translated-sales-channel'),
            'languageId' => $this->ids->get('language'),
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $this->ids->get('language')],
            ],
            'domains' => [[
                'languageId' => $this->ids->get('language'),
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost/garan-language-test',
            ]],
        ]);

        // the manufacturer is only written for the system language, so the sales channel language
        // above has to fall back to its parent language to resolve the brand name
        $blog = (new BlogBuilder($this->ids, 'garan-blog-translated'))
            ->price(10)
            ->visibility($this->ids->get('translated-sales-channel'))
            ->manufacturer('acme')
            ->build();
        $blog['manufacturerNumber'] = 'ACME-123';
        $blog['guaranteeMonths'] = 36;
        $blog['guaranteeConfirmed'] = true;

        static::getContainer()->get('blog.repository')->create([$blog], $context);

        $browser->request('GET', '/store-api/blog/' . $this->ids->get('garan-blog-translated') . '/garan-label');

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsString($response['svg']);
        static::assertStringContainsString('acme', $response['svg']);
        static::assertStringContainsString('ACME-123', $response['svg']);

        static::assertIsString($response['nestedSvg']);
        static::assertStringContainsString('3', $response['nestedSvg']);
    }
}
