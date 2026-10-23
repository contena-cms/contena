<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap;

use Contena\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Response;

class SitemapException extends HttpException
{
    public const FILE_NOT_READABLE = 'CONTENT__FILE_IS_NOT_READABLE';

    public const SITEMAP_ALREADY_LOCKED = 'CONTENT__SITEMAP_ALREADY_LOCKED';

    public const INVALID_DOMAIN = 'CONTENT__INVALID_DOMAIN';

    public static function fileNotReadable(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FILE_NOT_READABLE,
            'File is not readable at {{ path }}.',
            ['path' => $path]
        );
    }

    public static function sitemapAlreadyLocked(ChannelContext $context): self
    {
        return new AlreadyLockedException($context);
    }

    public static function invalidDomain(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOMAIN,
            'Invalid domain',
        );
    }
}
