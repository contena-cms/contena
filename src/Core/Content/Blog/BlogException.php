<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Currency\CurrencyEntity;
use Symfony\Component\HttpFoundation\Response;

class BlogException extends HttpException
{
    public const BLOG_INVALID_CHEAPEST_PRICE_FACADE = 'BLOG_INVALID_CHEAPEST_PRICE_FACADE';
    public const BLOG_PROXY_MANIPULATION_NOT_ALLOWED_CODE = 'BLOG_PROXY_MANIPULATION_NOT_ALLOWED';
    public const BLOG_INVALID_PRICE_DEFINITION_CODE = 'BLOG_INVALID_PRICE_DEFINITION';
    public const BLOG_NOT_FOUND = 'BLOG_BLOG_NOT_FOUND';
    public const BLOG_VARIANT_NOT_FOUND = 'CONTENT__BLOG_VARIANT_NOT_FOUND';
    public const CATEGORY_NOT_FOUND = 'BLOG__CATEGORY_NOT_FOUND';
    public const SORTING_NOT_FOUND = 'BLOG_SORTING_NOT_FOUND';
    public const LISTING_PAGE_OUT_OF_RANGE = 'BLOG__LISTING_PAGE_OUT_OF_RANGE';
    public const BLOG_CONFIGURATION_OPTION_ALREADY_EXISTS = 'BLOG_CONFIGURATION_OPTION_EXISTS_ALREADY';
    public const BLOG_INVALID_OPTIONS_PARAMETER = 'BLOG_INVALID_OPTIONS_PARAMETER';
    final public const BLOG_REVIEW_NOT_ACTIVE = 'BLOG__REVIEW_NOT_ACTIVE';
    final public const BLOG_ORIGINAL_ID_NOT_FOUND = 'BLOG__ORIGINAL_ID_NOT_FOUND';
    public const MISSING_REQUEST_PARAMETER_CODE = 'BLOG__MISSING_REQUEST_PARAMETER_CODE';

    public static function invalidCheapestPriceFacade(string $id): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::BLOG_INVALID_CHEAPEST_PRICE_FACADE,
            'Cheapest price facade for blog {{ id }} is invalid',
            ['id' => $id]
        );
    }

    public static function sortingNotFoundException(string $key): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SORTING_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'sorting', 'field' => 'key', 'value' => $key]
        );
    }

    public static function pageOutOfRange(int $requestedPage, int $lastPage): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LISTING_PAGE_OUT_OF_RANGE,
            'Requested listing page {{ requestedPage }} is out of range (last page: {{ lastPage }}).',
            ['requestedPage' => $requestedPage, 'lastPage' => $lastPage]
        );
    }

    public static function invalidPriceDefinition(): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::BLOG_INVALID_PRICE_DEFINITION_CODE,
            'Provided price definition is invalid.'
        );
    }

    public static function proxyManipulationNotAllowed(mixed $property): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::BLOG_PROXY_MANIPULATION_NOT_ALLOWED_CODE,
            'Manipulation of pricing proxy field {{ property }} is not allowed',
            ['property' => (string) $property]
        );
    }

    public static function categoryNotFound(string $categoryId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CATEGORY_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'category', 'field' => 'id', 'value' => $categoryId]
        );
    }

    public static function configurationOptionAlreadyExists(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BLOG_CONFIGURATION_OPTION_ALREADY_EXISTS,
            'Configuration option already exists'
        );
    }

    public static function invalidOptionsParameter(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BLOG_INVALID_OPTIONS_PARAMETER,
            'The parameter options is invalid.'
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function reviewNotActive(): self|ReviewNotActiveExeption
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new ReviewNotActiveExeption();
        }

        return new self(
            Response::HTTP_FORBIDDEN,
            self::BLOG_REVIEW_NOT_ACTIVE,
            'Reviews not activated'
        );
    }

    public static function originalIdNotFound(string $originalId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BLOG_ORIGINAL_ID_NOT_FOUND,
            'Cannot find originalId {{ originalId }} in listing mapping',
            ['originalId' => $originalId]
        );
    }

    public static function noPriceForCurrency(CurrencyEntity $currency): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            'BLOG__NO_PRICE_FOR_CURRENCY',
            'No price found for currency "{{ currency }}"',
            ['currency' => $currency->getName() ?? $currency->getShortName() ?? $currency->getIsoCode()]
        );
    }

    public static function blogNotFound(string $blogId): BlogNotFoundException
    {
        return new BlogNotFoundException($blogId);
    }

    /**
     * @param array<string> $options
     */
    public static function variantNotFound(string $blogId, array $options): VariantNotFoundException
    {
        return new VariantNotFoundException($blogId, $options);
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function missingRequestParameter(string $name): self|RoutingException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return RoutingException::missingRequestParameter($name);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name]
        );
    }
}
