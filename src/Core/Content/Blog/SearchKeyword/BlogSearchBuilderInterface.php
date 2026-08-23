<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\SearchKeyword;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface BlogSearchBuilderInterface
{
    public function build(Request $request, Criteria $criteria, ChannelContext $context): void;
}
