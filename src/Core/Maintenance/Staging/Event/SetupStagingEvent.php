<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Staging\Event;

use Contena\Core\Framework\Context;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 *
 * @phpstan-type DomainRewriteRule = array{match: string, type: string, replace: string}
 */
class SetupStagingEvent
{
    public const CONFIG_FLAG = 'core.staging';

    public bool $canceled = false;

    /**
     * @param list<DomainRewriteRule> $domainMappings
     * @param list<string> $extensionsToDisable
     * @param array<string, array<string, mixed>> $systemConfigOverrides
     */
    public function __construct(
        public readonly Context $context,
        public readonly SymfonyStyle $io,
        public readonly bool $disableMailDelivery = true,
        public readonly array $domainMappings = [],
        public readonly array $extensionsToDisable = [],
        public readonly array $systemConfigOverrides = [],
    ) {
    }
}
