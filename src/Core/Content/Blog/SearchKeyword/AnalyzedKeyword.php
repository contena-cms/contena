<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\SearchKeyword;

use Contena\Core\Framework\Struct\Struct;

class AnalyzedKeyword extends Struct
{
    public function __construct(
        protected string $keyword,
        protected float $ranking
    ) {
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function getRanking(): float
    {
        return $this->ranking;
    }

    public function setRanking(float $ranking): void
    {
        $this->ranking = $ranking;
    }

    public function getApiAlias(): string
    {
        return 'blog_search_keyword_analyzed';
    }
}
