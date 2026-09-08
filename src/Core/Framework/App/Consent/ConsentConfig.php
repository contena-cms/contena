<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Consent;

use Contena\Core\Framework\App\Feature\AppFeatureConfig;

/**
 * @internal
 *
 * @phpstan-type ConsentPayload array{name: string, scope: string, since: string, revision?: string|null}
 */
readonly class ConsentConfig implements AppFeatureConfig
{
    public const SINCE_FORMAT = 'Y-m-d';

    public function __construct(
        public string $name,
        public string $scope,
        public \DateTimeImmutable $since,
        public ?string $revision,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return ConsentPayload
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'scope' => $this->scope,
            'since' => $this->since->format(self::SINCE_FORMAT),
            'revision' => $this->revision,
        ];
    }
}
