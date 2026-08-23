<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Controller\AdminTagController;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AdminTagController::class)]
class AdminTagControllerTest extends TestCase
{
    public function testFilterIds(): void
    {
        $filterTagIdsService = static::createStub(FilterTagIdsService::class);
        $controller = new AdminTagController($filterTagIdsService);

        $response = $controller->filterIds(new Request(), new Criteria(), Context::createDefaultContext());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"total":0,"ids":[]}', $response->getContent());
    }
}
