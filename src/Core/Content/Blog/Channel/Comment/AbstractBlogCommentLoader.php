<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBlogCommentLoader
{
    abstract public function getDecorated(): AbstractBlogCommentLoader;

    abstract public function load(Request $request, ChannelContext $context, string $blogId): BlogCommentResult;
}
