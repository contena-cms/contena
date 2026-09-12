<?php declare(strict_types=1);

namespace Contena\Core\Framework;

use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ContextSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\DataAbstractionLayer\DataScope;
use Contena\Core\Framework\DataAbstractionLayer\DataScopeReadMode;
use Contena\Core\Framework\Struct\StateAwareTrait;
use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Attribute\Ignore;

class Context extends Struct
{
    use StateAwareTrait;

    final public const string SYSTEM_SCOPE = 'system';
    final public const string USER_SCOPE = 'user';
    final public const string CRUD_API_SCOPE = 'crud';

    final public const string SKIP_TRIGGER_FLOW = 'skipTriggerFlow';

    final public const string ELASTICSEARCH_EXPLAIN_MODE = 'explain-mode';

    final public const string SYSTEM_SCOPE_DAL_WRITE_EVENT = 'system-scope-dal-write-event';

    protected string $scope = self::USER_SCOPE;

    protected bool $rulesLocked = false;

    #[Ignore]
    protected array $extensions = [];

    /**
     * @var list<list<string>>
     */
    private array $scopeStates = [];

    private DataScope $dataScope;

    /**
     * @param non-empty-list<string> $languageIdChain
     * @param list<string> $ruleIds
     */
    public function __construct(
        protected ContextSource $source,
        protected array $languageIdChain = [Defaults::LANGUAGE_SYSTEM],
        protected string $versionId = Defaults::LIVE_VERSION,
        protected bool $considerInheritance = false,
        protected array $ruleIds = [],
        ?DataScope $dataScope = null,
        private DataScopeReadMode $dataScopeReadMode = DataScopeReadMode::Exact,
    ) {
        $this->dataScope = $dataScope ?? DataScope::platform();

        if ($source instanceof SystemSource) {
            $this->scope = self::SYSTEM_SCOPE;
        }

        // Should be already a valid language chain, but we will ensure it anyway
        $languageIdChain = array_values(array_filter($languageIdChain));
        if ($languageIdChain === []) {
            throw FrameworkException::invalidArgumentException('Argument "languageIdChain" must not be empty');
        }

        $this->languageIdChain = $languageIdChain;
    }

    /**
     * Extension are not serialized, as they could be anything and make problems during serialization,
     * for symfony serializer they are exlcuded by the #[Exclude] attribute already
     *
     * @return list<mixed>
     */
    public function __serialize(): array
    {
        return [
            $this->source,
            $this->languageIdChain,
            $this->versionId,
            $this->considerInheritance,
            $this->ruleIds,
            $this->scope,
            $this->states,
            $this->dataScope,
            $this->dataScopeReadMode,
        ];
    }

    /**
     * @param list<mixed> $data
     */
    public function __unserialize(array $data): void
    {
        [
            $this->source,
            $this->languageIdChain,
            $this->versionId,
            $this->considerInheritance,
            $this->ruleIds,
            $this->scope,
            $this->states,
            $this->dataScope,
            $this->dataScopeReadMode,
        ] = $data;
    }

    /**
     * Creates a context restricted to the explicit platform data scope.
     *
     * @internal
     */
    public static function createDefaultContext(?ContextSource $source = null): self
    {
        $source ??= new SystemSource();

        return new self($source, dataScope: DataScope::platform());
    }

    /**
     * Creates the platform management context with cross-scope read access.
     * Writes remain restricted to the platform data scope.
     */
    public static function createGlobalContext(?ContextSource $source = null): self
    {
        $source ??= new SystemSource();

        return new self(
            $source,
            dataScope: DataScope::platform(),
            dataScopeReadMode: DataScopeReadMode::All,
        );
    }

    public static function createCLIContext(?ContextSource $source = null): self
    {
        return self::createGlobalContext($source);
    }

    /**
     * Creates a context bound to a tenant. Reads on tenant-scoped entities are
     * automatically filtered by this tenant and writes inherit it.
     */
    public static function createTenantContext(string $tenantId, ?ContextSource $source = null): self
    {
        $source ??= new SystemSource();

        return new self($source, dataScope: DataScope::tenant($tenantId));
    }

    public function getDataScope(): DataScope
    {
        return $this->dataScope;
    }

    public function getDataScopeId(): string
    {
        return $this->dataScope->getId();
    }

    public function getDataScopeReadMode(): DataScopeReadMode
    {
        return $this->dataScopeReadMode;
    }

    public function allowsCrossScopeReads(): bool
    {
        return $this->dataScopeReadMode === DataScopeReadMode::All;
    }

    /**
     * Returns the tenant identity for tenant-domain operations. Data ownership
     * must use {@see getDataScopeId()} instead.
     */
    public function getTenantId(): ?string
    {
        return $this->dataScope->getTenantId();
    }

    public function getSource(): ContextSource
    {
        return $this->source;
    }

    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function getLanguageId(): string
    {
        return $this->languageIdChain[0];
    }

    /**
     * @return list<string>
     */
    public function getRuleIds(): array
    {
        return $this->ruleIds;
    }

    /**
     * @return non-empty-list<string>
     */
    public function getLanguageIdChain(): array
    {
        return $this->languageIdChain;
    }

    public function createWithVersionId(string $versionId): self
    {
        $context = new self(
            $this->source,
            $this->languageIdChain,
            $versionId,
            $this->considerInheritance,
            $this->ruleIds,
            $this->dataScope,
            $this->dataScopeReadMode,
        );
        $context->scope = $this->scope;

        foreach ($this->getExtensions() as $key => $extension) {
            $context->addExtension($key, $extension);
        }

        return $context;
    }

    /**
     * Creates an exact-scope copy while preserving the current context settings.
     */
    public function createWithDataScope(DataScope $dataScope): self
    {
        $context = new self(
            $this->source,
            $this->languageIdChain,
            $this->versionId,
            $this->considerInheritance,
            $this->ruleIds,
            $dataScope,
            DataScopeReadMode::Exact,
        );
        $context->scope = $this->scope;

        foreach ($this->getExtensions() as $key => $extension) {
            $context->addExtension($key, $extension);
        }

        return $context;
    }

    /**
     * Creates a copy bound to the given tenant while preserving the current context settings.
     */
    public function createWithTenantId(string $tenantId): self
    {
        return $this->createWithDataScope(DataScope::tenant($tenantId));
    }

    /**
     * Creates a copy with cross-scope read access while preserving the current context settings.
     * Writes remain restricted to the explicit platform scope.
     */
    public function createWithCrossScopeReadAccess(): self
    {
        $context = new self(
            $this->source,
            $this->languageIdChain,
            $this->versionId,
            $this->considerInheritance,
            $this->ruleIds,
            dataScope: DataScope::platform(),
            dataScopeReadMode: DataScopeReadMode::All,
        );
        $context->scope = $this->scope;

        foreach ($this->getExtensions() as $key => $extension) {
            $context->addExtension($key, $extension);
        }

        return $context;
    }

    /**
     * @template TReturn of mixed
     *
     * @param \Closure(Context): TReturn $callback
     * @param list<string> $states Temporary states that should exist only for this scope. Nested scopes do not inherit them unless they pass the same states again.
     *
     * @return TReturn the return value of the provided callback function
     */
    public function scope(string $scope, \Closure $callback, array $states = []): mixed
    {
        $currentScope = $this->getScope();
        $states = array_values(array_unique($states));

        // Merge all surrounding scope states so nested scopes can drop temporary states from every parent scope unless they opt in again.
        $outerScopeStates = array_values(array_unique(array_merge(...$this->scopeStates)));
        // States passed to this scope stay active; all other temporary states from parent scopes are hidden for this callback.
        $removeScopeStates = array_values(array_diff($outerScopeStates, $states));
        // Only states that were not already present are removed again when this scope exits.
        $addScopeStates = array_values(array_diff($states, $this->getStates()));

        $this->removeStates(...$removeScopeStates);
        $this->addState(...$addScopeStates);

        $this->scope = $scope;
        $this->scopeStates[] = $states;

        try {
            $result = $callback($this);
        } finally {
            array_pop($this->scopeStates);

            $this->removeStates(...$addScopeStates);
            $this->addState(...$removeScopeStates);

            $this->scope = $currentScope;
        }

        return $result;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function considerInheritance(): bool
    {
        return $this->considerInheritance;
    }

    public function setConsiderInheritance(bool $considerInheritance): void
    {
        $this->considerInheritance = $considerInheritance;
    }

    public function isAllowed(string $privilege): bool
    {
        if ($this->source instanceof AdminApiSource) {
            return $this->source->isAllowed($privilege);
        }

        return true;
    }

    /**
     * @param list<string> $ruleIds
     */
    public function setRuleIds(array $ruleIds): void
    {
        if ($this->rulesLocked) {
            throw FrameworkException::contextRulesLocked();
        }

        $this->ruleIds = array_values(array_filter($ruleIds));
    }

    public function lockRules(): void
    {
        $this->rulesLocked = true;
    }

    /**
     * @template TReturn of mixed
     *
     * @param \Closure(Context): TReturn $function
     *
     * @return TReturn
     */
    public function enableInheritance(\Closure $function): mixed
    {
        $previous = $this->considerInheritance;
        $this->considerInheritance = true;
        $result = $function($this);
        $this->considerInheritance = $previous;

        return $result;
    }

    /**
     * @template TReturn of mixed
     *
     * @param \Closure(Context): TReturn $function
     *
     * @return TReturn
     */
    public function disableInheritance(\Closure $function): mixed
    {
        $previous = $this->considerInheritance;
        $this->considerInheritance = false;
        $result = $function($this);
        $this->considerInheritance = $previous;

        return $result;
    }

    public function getApiAlias(): string
    {
        return 'context';
    }

    private function removeStates(string ...$states): void
    {
        foreach ($states as $state) {
            $this->removeState($state);
        }
    }
}
