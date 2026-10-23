<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractBlogReviewSaveRoute
{
    abstract public function getDecorated(): AbstractBlogReviewSaveRoute;

    abstract public function save(string $blogId, RequestDataBag $data, ChannelContext $context): NoContentResponse;
}
