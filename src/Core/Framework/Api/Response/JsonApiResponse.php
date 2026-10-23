<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class JsonApiResponse extends JsonResponse
{
    protected function update(): static
    {
        parent::update();

        $this->headers->set('Content-Type', 'application/vnd.api+json');

        return $this;
    }
}
