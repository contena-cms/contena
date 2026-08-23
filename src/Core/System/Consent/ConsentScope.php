<?php declare(strict_types=1);

namespace Contena\Core\System\Consent;

use Contena\Core\Framework\Context;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
interface ConsentScope
{
    public function getName(): string;

    /**
     * @throws ConsentException when scope cannot be resolved from the given context
     */
    public function resolveIdentifier(Context $context): string;

    /**
     * Should return the identifier of the user/admin who performed the action
     *
     * @throws ConsentException when scope cannot be resolved from the given context
     */
    public function resolveActorIdentifier(Context $context): string;
}
