<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary;

use Contena\Core\Framework\Context;

interface DataDictionaryLoaderInterface
{
    public function load(string $technicalName, Context $context): ?DataDictionaryEntity;
}
