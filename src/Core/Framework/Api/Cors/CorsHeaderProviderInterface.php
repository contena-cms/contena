<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Cors;

/**
 * Contributes header names to the global API CORS preflight response.
 *
 * Implementations are collected through the `contena.api.cors_header_provider` tag.
 */
interface CorsHeaderProviderInterface
{
    public const string SERVICE_TAG = 'contena.api.cors_header_provider';

    /** @return list<string> */
    public function getAllowedHeaders(): array;

    /** @return list<string> */
    public function getExposedHeaders(): array;
}
