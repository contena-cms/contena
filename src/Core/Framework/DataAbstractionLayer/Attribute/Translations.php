<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Translations extends Field
{
    public const string TYPE = 'translations';

    public function __construct()
    {
        parent::__construct(type: self::TYPE, api: true);
    }
}
