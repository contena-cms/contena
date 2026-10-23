<?php declare(strict_types=1);

namespace Contena\Core\Service\ServiceRegistry;


/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class SaveConsentRequest implements \JsonSerializable
{
    public function __construct(
        public string $identifier,
        public string $consentingUserId,
        public string $installationIdentifier,
        public string $consentDate,
        public string $consentRevision,
        public ?string $licenseHost = null,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'identifier' => $this->identifier,
            'consentingUserId' => $this->consentingUserId,
            'installationIdentifier' => $this->installationIdentifier,
            'consentDate' => $this->consentDate,
            'consentRevision' => $this->consentRevision,
            'licenseHost' => $this->licenseHost,
        ];
    }
}
