<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class NoConfiguratorFoundException extends ContenaHttpException
{
    public function __construct(string $blogId)
    {
        parent::__construct(
            'Blog with id {{ blogId }} has no configuration.',
            ['blogId' => $blogId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__BLOG_HAS_NO_CONFIGURATOR';
    }
}
