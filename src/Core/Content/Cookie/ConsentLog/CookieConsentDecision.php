<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ConsentLog;

/**
 * The verdict for one cookie group, derived by the server from the cookies the visitor ticked.
 *
 * @codeCoverageIgnore
 */
enum CookieConsentDecision: string
{
    /**
     * Every cookie the visitor could tick in the group was accepted.
     */
    case ACCEPTED = 'accepted';

    /**
     * Some, but not all, cookies of the group were accepted.
     */
    case PARTIAL = 'partial';

    /**
     * No cookie of the group was accepted.
     */
    case REJECTED = 'rejected';
}
