<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class ConvertGuestRouteTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->browser = $this->createCustomChannelBrowser([
            'id' => TestDefaults::CHANNEL,
        ]);

        $this->addCountriesToChannel();

        $this->assignChannelContext($this->browser);
        $this->userRepository = $this->getContainer()->get('user.repository');
    }

    public function testConvertGuestSuccess(): void
    {
        // Register as guest
        $this->register(true, 'guest@example.com');

        // Convert guest to registered user
        $this->browser->request(
            'POST',
            '/store-api/account/convert-guest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(
                ['password' => 'new-password'],
                \JSON_THROW_ON_ERROR,
            ),
        );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        // Verify user is no longer a guest
        $user = $this->userRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', 'guest@example.com')),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $user);
        static::assertFalse($user->getGuest());
    }

    public function testConvertGuestFailsForRegisteredUser(): void
    {
        // Register as regular user
        $this->register(false, 'registered@example.com', 'contena');

        // Try to convert registered user (should fail)
        $this->browser
            ->request(
                'POST',
                '/store-api/account/convert-guest',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(
                    ['password' => 'new-password'],
                    \JSON_THROW_ON_ERROR,
                ),
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertStringContainsString('is not a guest', $response['errors'][0]['detail']);
    }

    public function testConvertGuestFailsWithInvalidPassword(): void
    {
        // Register as guest
        $this->register(true, 'guest@example.com');

        // Try to convert with invalid password
        $this->browser
            ->request(
                'POST',
                '/store-api/account/convert-guest',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(
                    ['password' => ''], // Empty password should fail validation
                    \JSON_THROW_ON_ERROR,
                ),
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
    }

    private function register(bool $guest, string $email, ?string $password = null): void
    {
        $data = [
            'guest' => $guest,
            'email' => $email,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'frontendUrl' => 'http://localhost',
            'billingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'zipcode' => '12345',
                'city' => 'Schöppingen',
                'countryId' => $this->getValidCountryId(),
                'salutationId' => $this->getValidSalutationId(),
            ],
        ];

        if (!$guest && $password !== null) {
            $data['password'] = $password;
        }

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($data, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertNotNull($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }
}
