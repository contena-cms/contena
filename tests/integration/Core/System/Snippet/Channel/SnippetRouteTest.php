<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Snippet\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Snippet\SnippetException;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('channel-api')]
class SnippetRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private string $enGbSnippetSetId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $enGbSnippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        static::assertNotNull($enGbSnippetSetId);
        $this->enGbSnippetSetId = $enGbSnippetSetId;

        $deDeSnippetSetId = $this->getSnippetSetIdForLocale('de-DE');
        static::assertNotNull($deDeSnippetSetId);

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $this->getDeDeLanguageId()],
            ],
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $enGbSnippetSetId,
                    'url' => 'http://example.com',
                ],
                [
                    'languageId' => $this->getDeDeLanguageId(),
                    'snippetSetId' => $deDeSnippetSetId,
                    'url' => 'http://example.com/de',
                ],
            ],
        ]);
    }

    public function testReturnsResolvedSnippetsForTheContextLanguage(): void
    {
        $this->createSnippet('myCustom.test.key', 'Custom value');

        $this->browser->request('GET', '/channel-api/snippet');

        $response = $this->getJsonResponse();

        static::assertSame('snippet_set_result_list', $response['apiAlias']);
        // without languageIds the list contains exactly one set: the context language
        static::assertCount(1, $response['sets']);

        $set = $response['sets'][0];
        static::assertSame('snippet_set_result', $set['apiAlias']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $set['languageId']);
        static::assertSame('en-GB', $set['locale']);
        static::assertSame($this->enGbSnippetSetId, $set['snippetSetId']);
        static::assertIsString($set['hash']);
        static::assertNotSame('', $set['hash']);

        static::assertIsArray($set['snippets']);
        static::assertNotEmpty($set['snippets']);
        // the database override of the snippet set is part of the resolved map
        static::assertSame('Custom value', $set['snippets']['myCustom.test.key']);

        $etag = $this->browser->getResponse()->headers->get('ETag');
        static::assertSame('"' . $set['hash'] . '"', $etag);
    }

    public function testPrefixesLimitTheResultToWholeNamespaces(): void
    {
        $this->createSnippet('myPrefix.inside.key', 'Inside');
        $this->createSnippet('myPrefixOther.key', 'Outside');

        $this->browser->request('GET', '/channel-api/snippet?prefixes=myPrefix');

        $set = $this->getJsonResponse()['sets'][0];

        static::assertSame('Inside', $set['snippets']['myPrefix.inside.key'] ?? null);
        static::assertArrayNotHasKey('myPrefixOther.key', $set['snippets']);

        foreach (array_keys($set['snippets']) as $key) {
            static::assertTrue(
                $key === 'myPrefix' || str_starts_with((string) $key, 'myPrefix.'),
                \sprintf('Key "%s" does not belong to the requested namespace', $key)
            );
        }

        // a trailing dot is optional and must not change the content hash
        $this->browser->request('GET', '/channel-api/snippet?prefixes=myPrefix.');
        $setWithTrailingDot = $this->getJsonResponse()['sets'][0];

        static::assertSame($set['hash'], $setWithTrailingDot['hash']);
        static::assertSame($set['snippets'], $setWithTrailingDot['snippets']);
    }

    public function testEtagRevalidationReturnsNotModified(): void
    {
        $this->browser->request('GET', '/channel-api/snippet');

        $etag = $this->browser->getResponse()->headers->get('ETag');
        static::assertIsString($etag);

        $this->browser->request('GET', '/channel-api/snippet', [], [], ['HTTP_IF_NONE_MATCH' => $etag]);

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        static::assertSame('', (string) $response->getContent());
    }

    public function testMultipleLanguagesReturnOneSetPerLanguage(): void
    {
        $languageIds = implode(',', [Defaults::LANGUAGE_SYSTEM, $this->getDeDeLanguageId()]);

        $this->browser->request('GET', '/channel-api/snippet?languageIds=' . $languageIds);

        $response = $this->getJsonResponse();

        static::assertSame('snippet_set_result_list', $response['apiAlias']);
        static::assertCount(2, $response['sets']);

        $locales = array_column($response['sets'], 'locale', 'languageId');
        static::assertSame('en-GB', $locales[Defaults::LANGUAGE_SYSTEM]);
        static::assertSame('de-DE', $locales[$this->getDeDeLanguageId()]);

        foreach ($response['sets'] as $set) {
            static::assertSame('snippet_set_result', $set['apiAlias']);
            static::assertNotEmpty($set['snippets']);
        }
    }

    public function testFailsForALanguageNotAssignedToTheChannel(): void
    {
        $this->browser->request('GET', '/channel-api/snippet?languageIds=' . Uuid::randomHex());

        $response = $this->getJsonResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame(
            SnippetException::SNIPPET_LANGUAGE_NOT_AVAILABLE_IN_CHANNEL,
            $response['errors'][0]['code']
        );
    }

    private function createSnippet(string $translationKey, string $value): void
    {
        static::getContainer()->get('snippet.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'translationKey' => $translationKey,
                'value' => $value,
                'author' => 'testAuthor',
                'setId' => $this->enGbSnippetSetId,
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonResponse(): array
    {
        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
