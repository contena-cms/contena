<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationId;

use Contena\Core\Framework\App\AppException;

/**
 * @internal
 *
 * @phpstan-type InstallationIdConfig array{id: string, version: 2, fingerprints: array<string, string>}
 */
readonly class InstallationId implements \Stringable
{
    /**
     * @param array<string, string> $fingerprints
     */
    private function __construct(
        public string $id,
        public array $fingerprints = [],
        public int $version = 2,
    ) {
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function getFingerprint(string $identifier): ?string
    {
        return $this->fingerprints[$identifier] ?? null;
    }

    /**
     * @param array<string, string> $fingerprints
     */
    public static function create(string $id, array $fingerprints = []): self
    {
        return new self($id, $fingerprints, 2);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromSystemConfig(array $config): self
    {
        if (self::isValidConfig($config)) {
            return self::create($config['id'], $config['fingerprints']);
        }

        throw AppException::invalidInstallationIdConfiguration();
    }

    /**
     * @return array{
     *    id: string,
     *    fingerprints: array<string, string>,
     *    version: int
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fingerprints' => $this->fingerprints,
            'version' => $this->version,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function isValidConfig(array $config): bool
    {
        return isset($config['id'])
            && ($config['version'] ?? null) === 2
            && isset($config['fingerprints']);
    }
}
