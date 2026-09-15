<?php declare(strict_types=1);

namespace Contena\Core\System\OAuthClient\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;

/**
 * A list of 1–20 OAuth redirect URLs, preserved exactly for redirect matching.
 * Allows HTTPS and loopback HTTP, without credentials, fragments or wildcards.
 *
 * @codeCoverageIgnore
 */
class RedirectUriListField extends ListField
{
    protected function getSerializerClass(): string
    {
        return RedirectUriListFieldSerializer::class;
    }
}
