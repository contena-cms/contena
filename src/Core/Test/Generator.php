<?php declare(strict_types=1);

namespace Contena\Core\Test;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\MemberEntity;

/**
 * @internal
 */
class Generator extends TestCase
{
    final public const string TOKEN = 'test-token';
    final public const string DOMAIN = 'test-domain';
    final public const string NAVIGATION_CATEGORY = 'f8466865cc6a45e48ed98dd2f6a0a293';
    final public const string COUNTRY = 'd4eb3205dd9444169b3f60c056c313a1';
    final public const string LANGUAGE_INFO_NAME = 'English';
    final public const string LANGUAGE_INFO_LOCALE_CODE = 'en-GB';

    /**
     * @param array<string, string[]> $areaRuleIds
     * @param array<array-key, mixed> $overrides
     */
    public static function generateChannelContext(
        ?Context $baseContext = null,
        ?string $token = null,
        ?string $domainId = null,
        ?ChannelEntity $channel = null,
        ?MemberGroupEntity $currentMemberGroup = null,
        ?CountryEntity $country = null,
        ?MemberEntity $member = null,
        ?array $areaRuleIds = [],
        ?LanguageInfo $languageInfo = null,
        ?array $overrides = [],
    ): ChannelContext {
        $baseContext ??= Context::createDefaultContext();
        $token ??= self::TOKEN;
        $domainId ??= self::DOMAIN;

        if (!$currentMemberGroup) {
            $currentMemberGroup = new MemberGroupEntity();
            $currentMemberGroup->setId(TestDefaults::FALLBACK_MEMBER_GROUP);
        }

        if (!$country) {
            $country = new CountryEntity();
            $country->setId(self::COUNTRY);
        }

        if (!$channel) {
            $channel = new ChannelEntity();
            $channel->setId(TestDefaults::CHANNEL);
        }

        $channel->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $channel->setCountryId($country->getId());
        $channel->setCountry($country);
        $channel->setMemberGroupId($currentMemberGroup->getId());
        $channel->setMemberGroup($currentMemberGroup);
        $channel->setNavigationCategoryId(self::NAVIGATION_CATEGORY);
        $channel->setNavigationCategoryVersionId(Defaults::LIVE_VERSION);
        $channel->setNavigationCategoryDepth(2);

        $areaRuleIds ??= [];
        $languageInfo ??= self::createLanguageInfo();

        $channelContext = new ChannelContext(
            $baseContext,
            $token,
            $domainId,
            $channel,
            $currentMemberGroup,
            $country,
            $member,
            $languageInfo,
            $areaRuleIds,
        );

        if ($overrides) {
            $channelContext->assign($overrides);
        }

        return $channelContext;
    }

    public static function createLanguageInfo(
        ?string $name = null,
        ?string $localeCode = null,
    ): LanguageInfo {
        return new LanguageInfo(
            $name ?? self::LANGUAGE_INFO_NAME,
            $localeCode ?? self::LANGUAGE_INFO_LOCALE_CODE,
        );
    }
}
