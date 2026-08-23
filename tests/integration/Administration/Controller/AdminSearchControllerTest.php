<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Administration\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
class AdminSearchControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testSearchResultWithoutApiAwareField(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['user:read']);

        $this->getBrowser()->request('POST', '/api/_admin/search', [], [], [], json_encode([
            'user' => [
                'query' => [],
            ],
        ]) ?: null);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content, print_r($content, true));

        static::assertNotEmpty($content['data']['user']['data']);

        $user = array_values($content['data']['user']['data'])[0];

        static::assertSame('user', $user['apiAlias']);
        static::assertArrayNotHasKey('password', $user);
    }
}
