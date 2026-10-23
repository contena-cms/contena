<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\CustomField\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
class CustomFieldSetActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testGetAvailableRelations(): void
    {
        $this->getBrowser()->request('GET', '/api/_action/attribute-set/relations');
        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(200, $response->getStatusCode());
        static::assertSame('application/json', $response->headers->get('Content-Type'));

        $availableRelations = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($availableRelations);

        static::assertContains('blog', $availableRelations);
        static::assertNotContains('blog_translation', $availableRelations);

        static::assertContains('blog_manufacturer', $availableRelations);
        static::assertNotContains('blog-manufacturer', $availableRelations);
    }
}
