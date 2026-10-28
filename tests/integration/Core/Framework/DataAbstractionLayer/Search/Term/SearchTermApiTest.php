<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search\Term;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class SearchTermApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testASearchTermWithoutSearchableFieldsCanBeSortedByScore(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/search-ids/order-transaction', [
            'term' => 'anything',
            'sort' => [['field' => '_score', 'order' => 'DESC']],
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }
}
