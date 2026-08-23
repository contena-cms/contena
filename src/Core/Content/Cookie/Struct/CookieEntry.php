<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 *
 * Name and description can be provided as snippet keys or directly translated text.
 */
class CookieEntry extends Struct
{
    public ?string $value;

    public ?int $expiration;

    public ?string $name;

    public ?string $description;

    public bool $hidden = false;

    public function __construct(
        public string $cookie,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'cookie_entry';
    }
}
