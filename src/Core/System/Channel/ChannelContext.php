<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\UserEntity;

class ChannelContext extends Struct
{
    use StateAwareTrait;

    /**
     * @var array<string, bool>
     */
    protected array $permissions = [];

    /**
     * @deprecated tag:v6.8.0 - Property `permisionsLocked` will be removed, use `permissionsLocked` instead
     */
    protected bool $permisionsLocked = false;

    protected bool $permissionsLocked = false;

    protected ?string $imitatingUserId = null;

    /**
     * @internal
     *
     * @param array<string, array<string>> $areaRuleIds
     */
    public function __construct(
        protected Context $context,
        protected string $token,
        private ?string $domainId,
        protected ChannelEntity $channel,
        protected CurrencyEntity $currency,
        protected UserGroupEntity $currentUserGroup,
        protected TaxCollection $taxRules,
        protected ?UserEntity $user,
        protected CashRoundingConfig $itemRounding,
        protected CashRoundingConfig $totalRounding,
        protected LanguageInfo $languageInfo,
        protected array $areaRuleIds = [],
    ) {
    }

    public function getCurrentUserGroup(): UserGroupEntity
    {
        return $this->currentUserGroup;
    }

    public function getCurrency(): CurrencyEntity
    {
        return $this->currency;
    }

    public function getChannel(): ChannelEntity
    {
        return $this->channel;
    }

    public function getTaxRules(): TaxCollection
    {
        return $this->taxRules;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string>
     */
    public function getRuleIds(): array
    {
        return $this->context->getRuleIds();
    }

    /**
     * @param array<string> $ruleIds
     */
    public function setRuleIds(array $ruleIds): void
    {
        $this->context->setRuleIds($ruleIds);
    }

    /**
     * @internal
     *
     * @return array<string, array<string>>
     */
    public function getAreaRuleIds(): array
    {
        return $this->areaRuleIds;
    }

    /**
     * @internal
     *
     * @param array<string> $areas
     *
     * @return array<string>
     */
    public function getRuleIdsByAreas(array $areas): array
    {
        $ruleIds = [];

        foreach ($areas as $area) {
            if (!isset($this->areaRuleIds[$area]) || $this->areaRuleIds[$area] === []) {
                continue;
            }

            foreach ($this->areaRuleIds[$area] as $ruleId) {
                $ruleIds[$ruleId] = $ruleId;
            }
        }

        return array_values($ruleIds);
    }

    /**
     * @internal
     *
     * @param array<string, array<string>> $areaRuleIds
     */
    public function setAreaRuleIds(array $areaRuleIds): void
    {
        $this->areaRuleIds = $areaRuleIds;
    }

    public function lockRules(): void
    {
        $this->context->lockRules();
    }

    public function isPermissionsLocked(): bool
    {
        /** @deprecated tag:v6.8.0 - `$this->permisionsLocked` will be removed from condition without replacement */
        return $this->permissionsLocked || $this->permisionsLocked;
    }

    public function lockPermissions(): void
    {
        /** @deprecated tag:v6.8.0 - `$this->permisionsLocked` setter will be removed without replacement */
        $this->permisionsLocked = true;
        $this->permissionsLocked = true;
    }

    public function getToken(): string
    {
        /**
         * @see SwTwigFunction::getAttribute
         * Inside Twig rendering context, the token is not allowed to be accessed as it might expose sensitive information
         * when the data is dumped into outputted HTML.
         */
        if (FieldVisibility::$isInTwigRenderingContext) {
            throw ChannelException::contextTokenNotAccessible();
        }

        return $this->token;
    }

    public function getTaxState(): string
    {
        return $this->context->getTaxState();
    }

    public function setTaxState(string $taxState): void
    {
        $this->context->setTaxState($taxState);
    }

    public function getTaxCalculationType(): string
    {
        return $this->channel->getTaxCalculationType();
    }

    /**
     * @return array<string, bool>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * @param array<string, bool> $permissions
     */
    public function setPermissions(array $permissions): void
    {
        /** @deprecated tag:v6.8.0 - `$this->permisionsLocked` will be removed from condition without replacement */
        if ($this->permisionsLocked || $this->permissionsLocked) {
            throw ChannelException::contextPermissionsLocked();
        }

        $this->permissions = array_filter($permissions);
    }

    public function getApiAlias(): string
    {
        return 'channel_context';
    }

    public function hasPermission(string $permission): bool
    {
        return \array_key_exists($permission, $this->permissions) && $this->permissions[$permission];
    }

    public function getChannelId(): string
    {
        return $this->channel->getId();
    }

    public function addState(string ...$states): void
    {
        $this->context->addState(...$states);
    }

    public function removeState(string $state): void
    {
        $this->context->removeState($state);
    }

    public function hasState(string ...$states): bool
    {
        return $this->context->hasState(...$states);
    }

    /**
     * @return array<string>
     */
    public function getStates(): array
    {
        return $this->context->getStates();
    }

    public function state(\Closure $closure, string ...$states): mixed
    {
        return $this->context->state(fn () => $closure($this), ...$states);
    }

    public function getDomainId(): ?string
    {
        return $this->domainId;
    }

    public function setDomainId(?string $domainId): void
    {
        $this->domainId = $domainId;
    }

    /**
     * @return non-empty-list<string>
     */
    public function getLanguageIdChain(): array
    {
        return $this->context->getLanguageIdChain();
    }

    public function getLanguageId(): string
    {
        return $this->context->getLanguageId();
    }

    public function getVersionId(): string
    {
        return $this->context->getVersionId();
    }

    public function considerInheritance(): bool
    {
        return $this->context->considerInheritance();
    }

    public function getTotalRounding(): CashRoundingConfig
    {
        return $this->totalRounding;
    }

    public function setTotalRounding(CashRoundingConfig $totalRounding): void
    {
        $this->totalRounding = $totalRounding;
    }

    public function getItemRounding(): CashRoundingConfig
    {
        return $this->itemRounding;
    }

    public function setItemRounding(CashRoundingConfig $itemRounding): void
    {
        $this->itemRounding = $itemRounding;
    }

    public function getCurrencyId(): string
    {
        return $this->currency->getId();
    }

    public function ensureLoggedIn(bool $allowGuest = true): void
    {
        if ($this->user === null) {
            throw ChannelException::userNotLoggedIn();
        }

        if (!$allowGuest && $this->user->getGuest()) {
            throw ChannelException::userNotLoggedIn();
        }
    }

    public function getUserId(): ?string
    {
        return $this->user?->getId();
    }

    public function getImitatingUserId(): ?string
    {
        return $this->imitatingUserId;
    }

    public function setImitatingUserId(?string $imitatingUserId): void
    {
        $this->imitatingUserId = $imitatingUserId;
    }

    /**
     * @template TReturn of mixed
     *
     * @param callable(ChannelContext): TReturn $callback
     *
     * @return TReturn the return value of the provided callback function
     */
    public function live(callable $callback): mixed
    {
        $before = $this->context;

        $this->context = $this->context->createWithVersionId(Defaults::LIVE_VERSION);

        $result = $callback($this);

        $this->context = $before;

        return $result;
    }

    /**
     * Executed the callback function with the given permissions set in the ChannelContext. If the
     * permissions are locked, the callback is called with the original permissions of the ChannelContext.
     *
     * @template TReturn of mixed
     *
     * @param array<string, bool> $permissions
     * @param callable(ChannelContext): TReturn $callback
     *
     * @return TReturn the return value of the provided callback function
     */
    public function withPermissions(array $permissions, callable $callback): mixed
    {
        /** @deprecated tag:v6.8.0 - `$this->permisionsLocked` will be removed from condition without replacement */
        if ($this->permisionsLocked || $this->permissionsLocked) {
            return $callback($this);
        }

        $originalPermissions = $this->getPermissions();
        $permissions = array_merge($originalPermissions, $permissions);

        $this->setPermissions($permissions);

        $result = $callback($this);

        $this->setPermissions($originalPermissions);

        return $result;
    }

    public function getCountryId(): string
    {
        // Contact address country of the current user, falling back to the system default.
        return $this->user?->getActiveBillingAddress()?->getCountryId() ?? Defaults::COUNTRY;
    }

    public function getUserGroupId(): string
    {
        return $this->currentUserGroup->getId();
    }

    public function getLanguageInfo(): LanguageInfo
    {
        return $this->languageInfo;
    }

    public function setLanguageInfo(LanguageInfo $languageInfo): void
    {
        $this->languageInfo = $languageInfo;
    }
}
