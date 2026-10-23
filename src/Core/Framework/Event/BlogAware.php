<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Log\Package;

#[IsFlowEventAware]
interface BlogAware
{
    public const BLOG = 'blog';

    public const BLOG_ID = 'blogId';

    public function getBlogId(): string;
}
