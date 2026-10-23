<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Sso\AdminUserService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Sso\AdminUserService\ExternalAuthUser;
use Contena\Core\Framework\Sso\SsoException;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ExternalAuthUser::class)]
class ExternalAuthUserTest extends TestCase
{
    public function testCreate(): void
    {
        $expiry = new \DateTimeImmutable();
        $externalAuthUser = ExternalAuthUser::create(
            [
                'id' => 'id_value',
                'admin_user_id' => 'admin_user_id_value',
                'admin_user_sub' => 'admin_user_sub_value',
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => $expiry,
                'email' => 'test@example.com',
            ]
        );

        static::assertSame('id_value', $externalAuthUser->id);
        static::assertSame('admin_user_id_value', $externalAuthUser->admin_userId);
        static::assertSame('admin_user_sub_value', $externalAuthUser->sub);
        static::assertSame($expiry, $externalAuthUser->expiry);
        static::assertSame('test@example.com', $externalAuthUser->email);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('createTestDataProvider')]
    public function testCreateWithValidationErrors(array $data, string $expected): void
    {
        static::assertArrayHasKey('id', $data);
        static::assertArrayHasKey('admin_user_id', $data);
        static::assertArrayHasKey('admin_user_sub', $data);
        static::assertArrayHasKey('token', $data);
        static::assertArrayHasKey('expiry', $data);
        static::assertArrayHasKey('email', $data);

        $missingFields = \explode(', ', \substr($expected, \strlen('Login admin_user invalid: ')));
        $this->expectExceptionObject(SsoException::loginUserInvalid($missingFields));

        try {
            ExternalAuthUser::create($data);
        } catch (SsoException $exception) {
            static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
            static::assertSame(SsoException::SSO_LOGIN_USER_INVALID, $exception->getErrorCode());

            throw $exception;
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function createTestDataProvider(): iterable
    {
        yield 'all is null' => [
            'data' => [
                'id' => null,
                'admin_user_id' => null,
                'admin_user_sub' => null,
                'token' => null,
                'expiry' => null,
                'email' => null,
            ],
            'expected' => 'Login admin_user invalid: [id]: is required, [admin_user_id]: is required, [admin_user_sub]: is required, [email]: is required',
        ];
        yield 'all is blank' => [
            'data' => [
                'id' => '',
                'admin_user_id' => '',
                'admin_user_sub' => '',
                'token' => '',
                'expiry' => '',
                'email' => '',
            ],
            'expected' => 'Login admin_user invalid: [id]: is required, [admin_user_id]: is required, [admin_user_sub]: is required, [token]: Needs to be an array, [token]: This value should be of type array|(Traversable&ArrayAccess)., [expiry]: Needs to be a DateTimeInterface, [email]: is required',
        ];
        yield 'id is invalid' => [
            'data' => [
                'id' => 12,
                'admin_user_id' => 'admin_user_id',
                'admin_user_sub' => 'admin_user_sub',
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test@example.com',
            ],
            'expected' => 'Login admin_user invalid: [id]: This value should be of type string.',
        ];
        yield 'admin_user_id is invalid' => [
            'data' => [
                'id' => 'id',
                'admin_user_id' => 12,
                'admin_user_sub' => 'admin_user_sub',
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test@example.com',
            ],
            'expected' => 'Login admin_user invalid: [admin_user_id]: Needs to be a string',
        ];
        yield 'admin_user_sub is invalid' => [
            'data' => [
                'id' => 'id',
                'admin_user_id' => 'admin_user_id',
                'admin_user_sub' => 12,
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test@example.com',
            ],
            'expected' => 'Login admin_user invalid: [admin_user_sub]: Needs to be a string',
        ];
        yield 'token is invalid' => [
            'data' => [
                'id' => 'id',
                'admin_user_id' => 'admin_user_id',
                'admin_user_sub' => 'admin_user_sub',
                'token' => 12,
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test@example.com',
            ],
            'expected' => 'Login admin_user invalid: [token]: Needs to be an array, [token]: This value should be of type array|(Traversable&ArrayAccess).',
        ];
        yield 'expiry is invalid' => [
            'data' => [
                'id' => 'id',
                'admin_user_id' => 'admin_user_id',
                'admin_user_sub' => 'admin_user_sub',
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => '12-12-1212',
                'email' => 'test@example.com',
            ],
            'expected' => 'Login admin_user invalid: [expiry]: Needs to be a DateTimeInterface',
        ];
        yield 'email is invalid' => [
            'data' => [
                'id' => 'id',
                'admin_user_id' => 'admin_user_id',
                'admin_user_sub' => 'admin_user_sub',
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test.com',
            ],
            'expected' => 'Login admin_user invalid: [email]: Needs to be a valid email address',
        ];
    }

    public function testCreateFromDatabaseQuery(): void
    {
        $id = '01980cbe117f713088b2401b26b57275';
        $admin_userId = '01980cbe117f713088b2401b270fd2d5';
        $admin_userSub = 'admin_user_sub';

        $token = '1234567890abcdefghijklmnopqrstuvwxyz';
        $refreshToken = '0987654321zyxwvutsrqponmlkjihgfedcba';

        $expiry = '2025-07-15 06:20:39.679';

        $data = [
            'id' => Uuid::fromHexToBytes($id),
            'admin_user_id' => Uuid::fromHexToBytes($admin_userId),
            'admin_user_sub' => $admin_userSub,
            'token' => \json_encode(['token' => $token, 'refreshToken' => $refreshToken], \JSON_THROW_ON_ERROR),
            'expiry' => $expiry,
            'email' => 'test@test.com',
        ];

        $externalAuthUser = ExternalAuthUser::createFromDatabaseQuery($data);

        static::assertSame($id, $externalAuthUser->id);
        static::assertSame($admin_userId, $externalAuthUser->admin_userId);
        static::assertSame($admin_userSub, $externalAuthUser->sub);
        static::assertSame($token, $externalAuthUser->token?->token);
        static::assertSame($refreshToken, $externalAuthUser->token->refreshToken);
        static::assertSame($expiry, $externalAuthUser->expiry->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }
}
