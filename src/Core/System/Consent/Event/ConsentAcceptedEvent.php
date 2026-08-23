<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\Event;

readonly class ConsentAcceptedEvent
{
    public function __construct(
        public string $consentName,
        public string $consentScope,
        public string $identifier,
        public string $actor,
        public ?string $revision = null,
    ) {
    }

    public function getName(): string
    {
        return 'consent.' . $this->consentName . '.accepted';
    }
}
