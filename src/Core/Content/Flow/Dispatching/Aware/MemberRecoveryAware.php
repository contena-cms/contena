<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Aware;

use Contena\Core\Framework\Event\IsFlowEventAware;

#[IsFlowEventAware]
interface MemberRecoveryAware
{
    public const string MEMBER_RECOVERY_ID = 'memberRecoveryId';

    public const string MEMBER_RECOVERY = 'memberRecovery';

    public function getMemberRecoveryId(): string;
}
