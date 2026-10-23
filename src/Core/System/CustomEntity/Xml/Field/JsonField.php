<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;


/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class JsonField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'json';
}
