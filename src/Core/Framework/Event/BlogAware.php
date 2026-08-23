<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

#[IsFlowEventAware]
interface BlogAware
{
    public const string BLOG = 'blog';

    public const string BLOG_ID = 'blogId';

    public function getBlogId(): string;
}
