<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso\AdminUserService;

use League\OAuth2\Server\Entities\UserEntityInterface;
use Contena\Core\Framework\Sso\SsoException;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
final readonly class ExternalAuthAdminUser implements AdminUserEntityInterface
{
    /**
     * @param non-empty-string $admin_userId
     */
    private function __construct(
        public string $id,
        public string $admin_userId,
        public string $sub,
        public ?Token $token,
        public \DateTimeInterface $expiry,
        public string $email,
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->admin_userId;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): self
    {
        self::validate($data);

        return new self(
            $data['id'],
            $data['admin_user_id'],
            $data['admin_user_sub'],
            \array_key_exists('token', $data) && \is_array($data['token']) ? Token::fromArray($data['token']) : null,
            $data['expiry'],
            $data['email'],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createFromDatabaseQuery(array $data): self
    {
        $data['id'] = Uuid::fromBytesToHex($data['id']);
        $data['admin_user_id'] = Uuid::fromBytesToHex($data['admin_user_id']);
        $data['expiry'] = new \DateTimeImmutable($data['expiry']);
        if (\array_key_exists('token', $data) && \is_string($data['token'])) {
            $data['token'] = json_decode($data['token'], true);
        }

        return self::create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function validate(array $data): void
    {
        $violations = Validation::createValidator()->validate($data, self::createConstraints());
        if ($violations->count() === 0) {
            return;
        }

        $missingFields = [];
        foreach ($violations as $violation) {
            $missingFields[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        throw SsoException::loginUserInvalid($missingFields);
    }

    private static function createConstraints(): Collection
    {
        return new Collection([
            'id' => [
                new NotBlank(null, 'is required'),
                new Type('string'),
            ],
            'admin_user_id' => [
                new NotBlank(null, 'is required'),
                new Type('string', 'Needs to be a string'),
            ],
            'admin_user_sub' => [
                new NotBlank(null, 'is required'),
                new Type('string', 'Needs to be a string'),
            ],
            'token' => new Optional([
                new Type('array', 'Needs to be an array'),
                new Collection([
                    'token' => [
                        new NotBlank(null, 'is required'),
                        new Type('string', 'Needs to be a string'),
                    ],
                    'refreshToken' => [
                        new NotBlank(null, 'is required'),
                        new Type('string', 'Needs to be a string'),
                    ],
                ]),
            ]),
            'expiry' => [
                new Type('DateTimeInterface', 'Needs to be a DateTimeInterface'),
            ],
            'email' => [
                new NotBlank(null, 'is required'),
                new Email(null, 'Needs to be a valid email address'),
            ],
        ]);
    }
}
