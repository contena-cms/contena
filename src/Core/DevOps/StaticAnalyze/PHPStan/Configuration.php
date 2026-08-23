<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan;

/**
 * @internal
 */
final readonly class Configuration
{
    /**
     * @param array<string, list<string>> $parameters
     */
    public function __construct(private array $parameters)
    {
    }

    /**
     * @return list<string>
     */
    public function getAllowedNonDomainExceptions(): array
    {
        return $this->parameters['allowedNonDomainExceptions'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getAllowedFrontendRouteNamespaces(): array
    {
        return $this->parameters['allowedFrontendRouteNamespaces'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getAllowedUnitTestClassNamespaces(): array
    {
        return $this->parameters['allowedUnitTestClassNamespaces'] ?? [];
    }
}
