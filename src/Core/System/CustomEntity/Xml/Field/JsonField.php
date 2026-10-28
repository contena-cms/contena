<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class JsonField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'json';
}
