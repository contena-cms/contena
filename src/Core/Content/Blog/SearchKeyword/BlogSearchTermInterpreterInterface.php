<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\SearchKeyword;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Contena\Core\Framework\Log\Package;

interface BlogSearchTermInterpreterInterface
{
    public function interpret(string $word, Context $context): SearchPattern;
}
