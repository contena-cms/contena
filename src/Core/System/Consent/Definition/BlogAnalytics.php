<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\Definition;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Consent\ConsentDefinition;
use Contena\Core\System\Consent\ConsentScope;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class BlogAnalytics implements ConsentDefinition
{
    public function getName(): string
    {
        return 'blog_analytics';
    }

    public function getScopeName(): string
    {
        return ConsentScope\AdminUser::NAME;
    }

    public function getSince(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2025-12-12');
    }

    public function getRequiredPermissions(): array
    {
        return ['admin_user.update_profile'];
    }

    public function getLatestRevision(): ?string
    {
        return null;
    }
}
