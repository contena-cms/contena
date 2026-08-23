<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Twig\SwTwigFunction;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\MemberEntity;

class ChannelContext extends Struct
{
    /**
     * @var array<string, bool>
     */
    protected array $permissions = [];

    protected bool $permissionsLocked = false;

    protected ?string $imitatingUserId = null;

    /**
     * @param array<string, array<string>> $areaRuleIds
     */
    public function __construct(
        protected Context $context,
        protected string $token,
        private ?string $domainId,
        protected ChannelEntity $channel,
        protected MemberGroupEntity $currentMemberGroup,
        protected CountryEntity $country,
        protected ?MemberEntity $member,
        protected LanguageInfo $languageInfo,
        protected array $areaRuleIds = [],
    ) {
    }

    public function getCurrentMemberGroup(): MemberGroupEntity
    {
        return $this->currentMemberGroup;
    }

    public function getChannel(): ChannelEntity
    {
        return $this->channel;
    }

    public function getCountry(): CountryEntity
    {
        return $this->country;
    }

    public function getMember(): ?MemberEntity
    {
        return $this->member;
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
     * @param list<string> $ruleIds
     */
    public function setRuleIds(array $ruleIds): void
    {
        $this->context->setRuleIds($ruleIds);
    }

    /**
     * @return array<string, array<string>>
     */
    public function getAreaRuleIds(): array
    {
        return $this->areaRuleIds;
    }

    /**
     * @param array<string> $areas
     *
     * @return array<string>
     */
    public function getRuleIdsByAreas(array $areas): array
    {
        $ruleIds = [];

        foreach ($areas as $area) {
            if (($this->areaRuleIds[$area] ?? []) === []) {
                continue;
            }

            $ruleIds = array_unique(array_merge($ruleIds, $this->areaRuleIds[$area]));
        }

        return array_values($ruleIds);
    }

    /**
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
        return $this->permissionsLocked;
    }

    public function lockPermissions(): void
    {
        $this->permissionsLocked = true;
    }

    public function getToken(): string
    {
        /**
         * @see SwTwigFunction::getAttribute
         */
        if (FieldVisibility::$isInTwigRenderingContext) {
            throw ChannelException::contextTokenNotAccessible();
        }

        return $this->token;
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
        if ($this->permissionsLocked) {
            throw ChannelException::contextPermissionsLocked();
        }

        $this->permissions = array_filter($permissions);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions[$permission] ?? false;
    }

    /**
     * @param array<string, bool> $permissions
     */
    public function withPermissions(array $permissions, callable $callback): mixed
    {
        if ($this->permissionsLocked) {
            return $callback($this);
        }

        $originalPermissions = $this->getPermissions();
        $this->setPermissions(array_merge($originalPermissions, $permissions));

        try {
            return $callback($this);
        } finally {
            $this->setPermissions($originalPermissions);
        }
    }

    public function ensureLoggedIn(): void
    {
        if ($this->member === null) {
            throw ChannelException::memberNotLoggedIn();
        }
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

    public function getMemberId(): ?string
    {
        return $this->member?->getId();
    }

    public function getMemberGroupId(): string
    {
        return $this->currentMemberGroup->getId();
    }

    public function getCountryId(): string
    {
        return $this->country->getId();
    }

    public function getImitatingUserId(): ?string
    {
        return $this->imitatingUserId;
    }

    public function setImitatingUserId(?string $imitatingUserId): void
    {
        $this->imitatingUserId = $imitatingUserId;
    }

    public function live(callable $callback): mixed
    {
        $before = $this->context;
        $this->context = $this->context->createWithVersionId(Defaults::LIVE_VERSION);

        try {
            return $callback($this);
        } finally {
            $this->context = $before;
        }
    }

    public function getLanguageInfo(): LanguageInfo
    {
        return $this->languageInfo;
    }

    public function setLanguageInfo(LanguageInfo $languageInfo): void
    {
        $this->languageInfo = $languageInfo;
    }

    public function getApiAlias(): string
    {
        return 'channel_context';
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
}
