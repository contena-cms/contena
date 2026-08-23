<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Contena\Core\Framework\ContenaException;

interface WriteFieldException extends ContenaException
{
    public function getPath(): string;
}
