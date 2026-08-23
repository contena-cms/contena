<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\EntityProtection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\PluginDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures\PluginProtectionExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures\SystemConfigExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures\UserAccessKeyExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigDefinition;
use Contena\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;

/**
 * @internal
 */
class EntityProtectionValidatorTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        $this->registerDefinitionWithExtensions(PluginDefinition::class, PluginProtectionExtension::class);
        $this->registerDefinitionWithExtensions(SystemConfigDefinition::class, SystemConfigExtension::class);
        $this->registerDefinitionWithExtensions(UserAccessKeyDefinition::class, UserAccessKeyExtension::class);
    }

    #[DataProvider('blockedApiRequest')]
    #[Group('slow')]
    public function testItBlocksApiAccess(string $method, string $url): void
    {
        $this->getBrowser()
            ->jsonRequest(
                $method,
                '/api/' . $url
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(403, $response->getStatusCode(), $response->getContent());
    }

    /**
     * @return array<array<string>>
     */
    public static function blockedApiRequest(): array
    {
        return [
            ['GET', 'plugin/' . Uuid::randomHex()], // detail
            ['GET', 'plugin'], // list
            ['POST', 'plugin'], // create
            ['PATCH', 'plugin/' . Uuid::randomHex()], // update
            ['DELETE', 'plugin/' . Uuid::randomHex()], // delete
            ['POST', 'search/plugin'], // search
            ['POST', 'search-ids/plugin'], // search ids

            // nested routes
            ['POST', 'search/user/' . Uuid::randomHex() . '/access-keys'], // search
            ['POST', 'search-ids/user/' . Uuid::randomHex() . '/access-keys'], // search ids
        ];
    }

    public function testItAllowsReadsOnEntitiesWithWriteProtectionOnly(): void
    {
        $this->getBrowser()
            ->jsonRequest(
                'GET',
                '/api/system-config'
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertNotSame(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->jsonRequest(
                'GET',
                '/api/system-config/' . Uuid::randomHex()
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertNotSame(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/system-config'
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(403, $response->getStatusCode(), $response->getContent());
    }

    public function testItBlocksReadsOnForbiddenAssociations(): void
    {
        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/search/user',
                [
                    'associations' => [
                        'accessKeys' => [],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/search/user',
                [
                    'associations' => [
                        'avatarMedia' => [],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertNotSame(403, $response->getStatusCode(), $response->getContent());
    }

    public function testItBlocksReadsOnForbiddenNestedAssociations(): void
    {
        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/search/media',
                [
                    'associations' => [
                        'user' => [
                            'associations' => [
                                'accessKeys' => [],
                            ],
                        ],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/search/media',
                [
                    'associations' => [
                        'user' => [
                            'associations' => [
                                'avatarMedia' => [],
                            ],
                        ],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertNotSame(403, $response->getStatusCode(), $response->getContent());
    }
}
