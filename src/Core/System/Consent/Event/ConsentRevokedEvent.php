<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Hookable;

readonly class ConsentRevokedEvent implements Hookable
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

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'consentName' => $this->consentName,
            'consentScope' => $this->consentScope,
            'identifier' => $this->identifier,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $permissions->isAllowed('consent:' . $this->consentName, 'read');
    }
}
