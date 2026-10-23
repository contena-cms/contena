<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogDownload;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogDownloadEntity>
 *
 * @codeCoverageIgnore
 */
class BlogDownloadCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_download_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogDownloadEntity::class;
    }
}
