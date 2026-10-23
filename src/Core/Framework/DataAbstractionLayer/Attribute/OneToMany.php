<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

use Contena\Core\Framework\Log\Package;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OneToMany extends Field
{
    public const TYPE = 'one-to-many';

    public function __construct(
        public string $entity,
        public string $ref,
        public OnDelete $onDelete = OnDelete::NO_ACTION,
        public bool|array $api = false
    ) {
        parent::__construct(type: self::TYPE, api: $api);
    }
}
