<?php declare(strict_types=1);

namespace Contena\Core\Content\Category;

use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

class CategoryException extends HttpException
{
    public const SERVICE_CATEGORY_NOT_FOUND = 'CHECKOUT__SERVICE_CATEGORY_NOT_FOUND';
    public const FOOTER_CATEGORY_NOT_FOUND = 'CHECKOUT__FOOTER_CATEGORY_NOT_FOUND';
    public const AFTER_CATEGORY_NOT_FOUND = 'CONTENT__AFTER_CATEGORY_NOT_FOUND';
    public const CMS_PAGE_NOT_FOUND = 'CONTENT__CMS_PAGE_NOT_FOUND';

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function pageNotFound(string $pageId): ContenaHttpException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new PageNotFoundException($pageId);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::CMS_PAGE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'page', 'field' => 'ID', 'value' => $pageId]
        );
    }

    public static function categoryNotFound(string $id): ContenaHttpException
    {
        return new CategoryNotFoundException($id);
    }

    public static function serviceCategoryNotFoundForChannel(string $channelName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_CATEGORY_NOT_FOUND,
            'Service category, for sales channel {{ channelName }}, is not set',
            ['channelName' => $channelName]
        );
    }

    public static function footerCategoryNotFoundForChannel(string $channelName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FOOTER_CATEGORY_NOT_FOUND,
            'Footer category, for sales channel {{ channelName }}, is not set',
            ['channelName' => $channelName]
        );
    }

    public static function afterCategoryNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::AFTER_CATEGORY_NOT_FOUND,
            'Category to insert after not found.',
        );
    }
}
