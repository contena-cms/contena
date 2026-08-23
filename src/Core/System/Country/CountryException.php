<?php declare(strict_types=1);

namespace Contena\Core\System\Country;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class CountryException extends HttpException
{
    public const string COUNTRY_NOT_FOUND = 'CHECKOUT__COUNTRY_NOT_FOUND';

    public static function countryNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COUNTRY_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'country', 'field' => 'id', 'value' => $id]
        );
    }
}
