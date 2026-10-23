<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;

class ChildCountField extends IntField
{
    public function __construct()
    {
        parent::__construct('child_count', 'childCount');
        $this->addFlags(new WriteProtected(Context::SYSTEM_SCOPE));
    }
}
