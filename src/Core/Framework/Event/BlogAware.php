<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;


#[IsFlowEventAware]
interface BlogAware
{
    public const BLOG = 'blog';

    public const BLOG_ID = 'blogId';

    public function getBlogId(): string;
}
