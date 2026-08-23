<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule\Api;

use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class RuleConfigController extends AbstractController
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $config = [];

    /**
     * @internal
     *
     * @param iterable<Rule> $rules
     */
    public function __construct(iterable $rules)
    {
        foreach ($rules as $rule) {
            try {
                $config = $rule->getConfig();
            } catch (\Throwable) {
                continue;
            }

            if ($config !== null) {
                $this->config[$rule->getName()] = $config->getData();
            }
        }
    }

    #[Route(path: '/api/_info/rule-config', name: 'api.info.rule-config', methods: ['GET'])]
    public function getConditionsConfig(): JsonResponse
    {
        return new JsonResponse($this->config);
    }
}
