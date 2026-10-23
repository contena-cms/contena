<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBlogReviewLoader
{
    abstract public function getDecorated(): AbstractBlogReviewLoader;

    abstract public function load(
        Request $request,
        ChannelContext $context,
        string $blogId,
        ?string $blogParentId = null
    ): BlogReviewResult;
}
