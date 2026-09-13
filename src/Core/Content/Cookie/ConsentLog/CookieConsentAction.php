<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ConsentLog;

/**
 * The interaction a visitor performed on the cookie banner.
 *
 * @codeCoverageIgnore
 */
enum CookieConsentAction: string
{
    case ACCEPT_ALL = 'accept_all';
    case ACCEPT_REQUIRED = 'accept_required';
    case ACCEPT_SELECTED = 'accept_selected';
}
