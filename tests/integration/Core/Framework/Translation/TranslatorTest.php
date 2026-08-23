<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Contena\Core\System\Snippet\Files\SnippetFileCollection;
use Contena\Core\System\Snippet\SnippetCollection;
use Contena\Core\System\Snippet\SnippetDefinition;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * @internal
 */
class TranslatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Translator $translator;

    /**
     * @var EntityRepository<SnippetCollection>
     */
    private EntityRepository $snippetRepository;

    /**
     * @var EntityRepository<SnippetSetCollection>
     */
    private EntityRepository $snippetSetRepository;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    protected function setUp(): void
    {
        $this->translator = static::getContainer()->get(Translator::class);
        $this->snippetRepository = static::getContainer()->get('snippet.repository');
        $this->snippetSetRepository = static::getContainer()->get('snippet_set.repository');
        $this->languageRepository = static::getContainer()->get('language.repository');
        $this->localeRepository = static::getContainer()->get('locale.repository');

        $this->translator->reset();
        $this->translator->warmUp('');
    }

    public function testPassthrough(): void
    {
        $file = new TranslatorTestSnippetFile();
        static::getContainer()->get(SnippetFileCollection::class)->add($file);

        $stack = static::getContainer()->get(RequestStack::class);
        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $this->getSnippetSetIdForLocale('en-GB'));
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, 'en-GB');
        $stack->push($request);

        static::assertSame('Blog', $this->translator->getCatalogue('en-GB')->get('frontend.blog.title'));

        static::assertSame($request, $stack->pop());
    }

    public function testSimpleOverwrite(): void
    {
        $context = Context::createDefaultContext();
        $snippet = [
            'translationKey' => 'new.unit.test.key',
            'value' => 'Translated blog text',
            'setId' => $this->getSnippetSetIdForLocale('en-GB'),
            'author' => 'Contena',
        ];
        $this->snippetRepository->create([$snippet], $context);

        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $snippet['setId']);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, 'en-GB');
        static::getContainer()->get(RequestStack::class)->push($request);

        static::assertSame($snippet['value'], $this->translator->getCatalogue('en-GB')->get($snippet['translationKey']));
        static::assertSame($request, static::getContainer()->get(RequestStack::class)->pop());
    }

    public function testSymfonyDefaultTranslationFallback(): void
    {
        $this->translator->reset();
        $catalogue = $this->translator->getCatalogue('en-GB');
        static::assertInstanceOf(MessageCatalogueInterface::class, $catalogue->getFallbackCatalogue());
        static::assertSame('en', $catalogue->getFallbackCatalogue()->getLocale());
    }

    public function testFallbackOnParentLanguageSnippets(): void
    {
        $context = Context::createDefaultContext();
        $localeId = Uuid::randomHex();
        $this->localeRepository->create([[
            'id' => $localeId,
            'code' => 'en-US',
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'English US',
                    'territory' => 'United States',
                ],
            ],
        ]], $context);
        $this->snippetSetRepository->create([
            ['name' => 'en-US', 'baseFile' => 'messages.en-US', 'iso' => 'en-US'],
        ], $context);

        $snippet = [
            'translationKey' => 'parent.language.blog',
            'value' => 'Inherited blog text',
            'setId' => $this->getSnippetSetIdForLocale('en-US'),
            'author' => 'Contena',
        ];
        $this->snippetRepository->create([$snippet], $context);

        $languageId = Uuid::randomHex();
        $this->languageRepository->create([[
            'id' => $languageId,
            'name' => 'English US',
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'active' => true,
            'localeId' => $localeId,
            'translationCodeId' => $localeId,
        ]], $context);

        $this->translator->injectSettings(TestDefaults::CHANNEL, $languageId, 'en-US', $context);

        $catalogue = $this->translator->getCatalogue('en-US');
        static::assertNotNull($catalogue->getFallbackCatalogue());
        static::assertSame('en-GB', $catalogue->getFallbackCatalogue()->getLocale());
        static::assertSame($snippet['value'], $this->translator->trans($snippet['translationKey']));
    }

    public function testDeleteSnippet(): void
    {
        $snippet = [
            'id' => Uuid::randomHex(),
            'translationKey' => 'delete.me',
            'value' => 'temporary',
            'setId' => $this->getSnippetSetIdForLocale('en-GB'),
            'author' => 'Contena',
        ];

        $created = $this->snippetRepository->create([$snippet], Context::createDefaultContext())
            ->getEventByEntityName(SnippetDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $created);
        static::assertSame([$snippet['id']], $created->getIds());

        $deleted = $this->snippetRepository->delete([['id' => $snippet['id']]], Context::createDefaultContext())
            ->getEventByEntityName(SnippetDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $deleted);
        static::assertSame([$snippet['id']], $deleted->getIds());
    }

    public function testItReplacesReservedCharacter(): void
    {
        static::assertSame('translator.<_r_strong>', Translator::buildName('</strong>'));
    }

    #[DataProvider('pluralTranslationProvider')]
    public function testPluralRules(string $expected, string $id, int $number, string $locale): void
    {
        static::assertSame($expected, $this->translator->trans($id, ['%count%' => (string) $number], null, $locale));
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function pluralTranslationProvider(): iterable
    {
        yield 'English zero' => ['There are 0 posts', 'There is one post|There are %count% posts', 0, 'en-GB'];
        yield 'English one' => ['There is one post', 'There is one post|There are %count% posts', 1, 'en-GB'];
        yield 'English many' => ['There are 2 posts', 'There is one post|There are %count% posts', 2, 'en-GB'];
    }
}
