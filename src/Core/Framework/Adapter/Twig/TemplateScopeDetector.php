<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig;

use Contena\Core\Framework\Adapter\AdapterException;
use Contena\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\RequestStack;

#[Package('framework')]
#[BecomesFinal(version: 'v6.8.0')]
class TemplateScopeDetector
{
    public const SCOPES_ATTRIBUTE = '_templateScopes';
    public const DEFAULT_SCOPE = 'default';

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return string[]
     */
    public function getScopes(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return [self::DEFAULT_SCOPE];
        }

        $scope = $request->attributes->get(self::SCOPES_ATTRIBUTE);
        if (\is_string($scope)) {
            return [$scope];
        }

        if (\is_array($scope)) {
            return $scope;
        }

        if (!$scope) {
            return [self::DEFAULT_SCOPE];
        }

        throw AdapterException::invalidTemplateScope($scope);
    }
}
