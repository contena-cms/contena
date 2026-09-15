<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;

abstract class AbstractBlogCommentSaveRoute
{
    abstract public function getDecorated(): AbstractBlogCommentSaveRoute;

    abstract public function save(string $blogId, RequestDataBag $data, ChannelContext $context): NoContentResponse;
}
