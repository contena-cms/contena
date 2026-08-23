<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\SearchKeyword;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;

interface BlogSearchTermInterpreterInterface
{
    public function interpret(string $word, Context $context): SearchPattern;
}
