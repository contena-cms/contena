<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class SearchControllerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    #[DataProvider('invalidSearchTerms')]
    public function testSearchEscapesSearchTerm(string $term): void
    {
        $response = $this->request('GET', 'search', ['search' => $term]);
        $html = $response->getContent();

        static::assertSame(200, $response->getStatusCode(), (string) $html);
        static::assertIsString($html);
        static::assertStringNotContainsString($term, $html);
        static::assertStringContainsString(htmlentities($term), $html);
    }

    public function testSearchRendersGenericStructuredData(): void
    {
        $response = $this->request('GET', 'search', ['search' => 'test']);
        $html = $response->getContent();

        static::assertSame(200, $response->getStatusCode(), (string) $html);
        static::assertIsString($html);
        static::assertStringContainsString('"@type": "SearchResultsPage"', $html);
        static::assertStringContainsString('"isFamilyFriendly": false', $html);
    }

    public static function invalidSearchTerms(): \Generator
    {
        yield 'html heading' => ['<h1 style="color:red">Test</h1>'];
        yield 'script tag' => ['<script type="text/javascript">javascript:alert(1);</script>'];
        yield 'image event handler' => ['<img src=1 href=1 onerror="javascript:alert(1)"></img>'];
        yield 'svg event handler' => ['<svg onResize="javascript:alert(1)"></svg>'];
        yield 'quoted image payload' => ['"/><img/onerror=javascript:alert(1) src=xxx:x />'];
    }
}
