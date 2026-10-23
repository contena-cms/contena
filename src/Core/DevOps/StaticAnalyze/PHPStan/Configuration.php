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

    /**
     * @return list<string>
     */
    public function getCreateMockWithoutExpectationsEnabledNamespaces(): array
    {
        return $this->parameters['createMockWithoutExpectationsEnabledNamespaces'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getFeatureSkipInUnitTestsEnabledNamespaces(): array
    {
        return $this->parameters['featureSkipInUnitTestsEnabledNamespaces'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getKernelInUnitTestsEnabledNamespaces(): array
    {
        return $this->parameters['kernelInUnitTestsEnabledNamespaces'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getReflectionInUnitTestsEnabledNamespaces(): array
    {
        return $this->parameters['reflectionInUnitTestsEnabledNamespaces'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getRuntimeListenerOnSharedEventDispatcherEnabledNamespaces(): array
    {
        return $this->parameters['runtimeListenerOnSharedEventDispatcherEnabledNamespaces'] ?? [];
    }
}
