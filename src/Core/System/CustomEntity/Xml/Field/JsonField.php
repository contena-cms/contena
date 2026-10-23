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
use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

    protected string $type = 'json';
}
