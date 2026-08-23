<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\ConsentScope;

use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\System\Consent\ConsentException;
use Contena\Core\System\Consent\ConsentScope;

/**
 * Scope for consents given by anonymous frontend visitors.
 *
 * Visitors are intentionally not identified (privacy by design), so the
 * identifier is always the literal `anonymous`. Consent evidence for this
 * scope is stored in dedicated log tables, not in the consent state storage.
 *
 * @internal
 */
class FrontendVisitor implements ConsentScope
{
    public const NAME = 'frontend_visitor';

    public const IDENTIFIER = 'anonymous';

    public function getName(): string
    {
        return self::NAME;
    }

    public function resolveIdentifier(Context $context): string
    {
        if (!$context->getSource() instanceof ChannelApiSource) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        return self::IDENTIFIER;
    }

    public function resolveActorIdentifier(Context $context): string
    {
        return $this->resolveIdentifier($context);
    }
}
