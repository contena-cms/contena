<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context;

use Contena\Core\Framework\Log\Package;

class SystemSource implements ContextSource
{
    public string $type = 'system';
}
