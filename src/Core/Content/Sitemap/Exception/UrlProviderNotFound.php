<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

#[Package('discovery')]
class UrlProviderNotFound extends ContenaHttpException
{
    public function __construct(string $provider)
    {
        parent::__construct('provider "{{ provider }}" not found.', ['provider' => $provider]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_PROVIDER_NOT_FOUND';
    }
}
