<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\Event;

readonly class ConsentRevokedEvent
{
    public function __construct(
        public string $consentName,
        public string $consentScope,
        public string $identifier,
        public string $actor,
    ) {
    }

    public function getName(): string
    {
        return 'consent.' . $this->consentName . '.revoked';
    }
}
