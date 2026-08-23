<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap;

use Contena\Core\Content\Sitemap\Exception\InvalidSitemapKey;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Response;

class SitemapException extends HttpException
{
    public const string FILE_NOT_READABLE = 'CONTENT__FILE_IS_NOT_READABLE';

    public const string SITEMAP_ALREADY_LOCKED = 'CONTENT__SITEMAP_ALREADY_LOCKED';

    public const string INVALID_DOMAIN = 'CONTENT__INVALID_DOMAIN';

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
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SITEMAP_ALREADY_LOCKED,
            'Cannot acquire lock for channel {{ channelId }} and language {{ languageId }}',
            [
                'channelId' => $context->getChannelId(),
                'languageId' => $context->getLanguageId(),
            ],
        );
    }

    public static function invalidDomain(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOMAIN,
            'Invalid domain',
        );
    }

    public static function invalidKey(string $sitemapKey): ContenaHttpException
    {
        return new InvalidSitemapKey($sitemapKey);
    }
}
