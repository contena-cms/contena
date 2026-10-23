<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter;

use Contena\Core\Framework\Context;
use Symfony\Contracts\Service\ResetInterface;

abstract class AbstractTokenFilter implements ResetInterface
{
    final public const DEFAULT_MIN_SEARCH_TERM_LENGTH = 2;

    public function reset(): void
    {
        $this->getDecorated()->reset();
    }

    abstract public function getDecorated(): AbstractTokenFilter;

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    abstract public function filter(array $tokens, Context $context): array;
}
