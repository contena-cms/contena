<?php declare(strict_types=1);

namespace Contena\Core\Service\Requirement;


/**
 * @internal
 *
 * A condition on a service. Its {@see Gate} says what it controls: whether the service may be
 * installed at all, or whether an installed service may run.
 */
interface ServiceRequirement
{
    public static function getName(): string;

    public function getGate(): Gate;

    public function isSatisfied(): bool;

    /**
     * Whether services declaring this requirement may have their active state changed manually
     * (Admin UI, API). When false, the service's state is managed exclusively through requirement
     * evaluation.
     */
    public function permitsStateChange(): bool;
}
