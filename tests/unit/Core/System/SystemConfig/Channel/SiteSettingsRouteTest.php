<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\SystemConfig\Channel\SiteSettingsRoute;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(SiteSettingsRoute::class)]
class SiteSettingsRouteTest extends TestCase
{
    public function testLoadReturnsChannelResolvedSettings(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.basicInformation.siteName' => 'Contena',
                'core.basicInformation.metaAuthor' => 'Contena',
                'core.basicInformation.metaRobots' => 'index,follow',
                'core.basicInformation.familyFriendly' => true,

                'core.loginRegistration.passwordMinLength' => '12',
                'core.loginRegistration.showTitleField' => true,
                'core.loginRegistration.requireEmailConfirmation' => true,
                'core.loginRegistration.requirePasswordConfirmation' => true,
                'core.loginRegistration.doubleOptInRegistration' => true,
                'core.loginRegistration.showPhoneNumberField' => true,
                'core.loginRegistration.phoneNumberFieldRequired' => true,
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => true,
                'core.loginRegistration.showAdditionalAddressField1' => true,
                'core.loginRegistration.additionalAddressField1Required' => true,
                'core.loginRegistration.showAdditionalAddressField2' => true,
                'core.loginRegistration.additionalAddressField2Required' => true,
                'core.loginRegistration.addressInputFieldArrangement' => 'zip-city-region',
                'core.loginRegistration.allowMemberDeletion' => true,
                'core.loginRegistration.requireDataProtectionCheckbox' => true,
            ],
        ]);

        $route = new SiteSettingsRoute($systemConfigService);

        $settings = $route->load(Generator::generateChannelContext())->getSettings();

        static::assertSame('site_settings', $settings->getApiAlias());

        $general = $settings->general;
        static::assertSame('Contena', $general->siteName);
        static::assertSame('Contena', $general->metaAuthor);
        static::assertSame('index,follow', $general->metaRobots);
        static::assertTrue($general->familyFriendly);

        $loginRegistration = $settings->loginRegistration;
        static::assertSame(12, $loginRegistration->passwordMinLength);
        static::assertTrue($loginRegistration->showTitleField);
        static::assertTrue($loginRegistration->requireEmailConfirmation);
        static::assertTrue($loginRegistration->requirePasswordConfirmation);
        static::assertTrue($loginRegistration->doubleOptInRegistration);
        static::assertTrue($loginRegistration->showPhoneNumberField);
        static::assertTrue($loginRegistration->phoneNumberFieldRequired);
        static::assertTrue($loginRegistration->showBirthdayField);
        static::assertTrue($loginRegistration->birthdayFieldRequired);
        static::assertTrue($loginRegistration->showAdditionalAddressField1);
        static::assertTrue($loginRegistration->additionalAddressField1Required);
        static::assertTrue($loginRegistration->showAdditionalAddressField2);
        static::assertTrue($loginRegistration->additionalAddressField2Required);
        static::assertSame('zip-city-region', $loginRegistration->addressInputFieldArrangement);
        static::assertTrue($loginRegistration->allowMemberDeletion);
        static::assertTrue($loginRegistration->requireDataProtectionCheckbox);
    }

    public function testLoadFallsBackToUnsetDefaultsWhenConfigIsEmpty(): void
    {
        $route = new SiteSettingsRoute(new StaticSystemConfigService());

        $settings = $route->load(Generator::generateChannelContext())->getSettings();

        static::assertSame('', $settings->general->siteName);
        static::assertSame('', $settings->general->metaRobots);
        static::assertFalse($settings->general->familyFriendly);

        static::assertSame(0, $settings->loginRegistration->passwordMinLength);
        static::assertFalse($settings->loginRegistration->showTitleField);
        static::assertSame('', $settings->loginRegistration->addressInputFieldArrangement);
        static::assertFalse($settings->loginRegistration->allowMemberDeletion);
    }

    public function testLoadDoesNotLeakConfigOfOtherChannels(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '10',
                'core.loginRegistration.allowMemberDeletion' => false,
            ],
            'other-channel-id' => [
                'core.loginRegistration.passwordMinLength' => '99',
                'core.loginRegistration.allowMemberDeletion' => true,
            ],
        ]);

        $route = new SiteSettingsRoute($systemConfigService);

        $settings = $route->load(Generator::generateChannelContext())->getSettings();

        static::assertSame(10, $settings->loginRegistration->passwordMinLength);
        static::assertFalse($settings->loginRegistration->allowMemberDeletion);
    }

    public function testGetDecoratedThrows(): void
    {
        $route = new SiteSettingsRoute(new StaticSystemConfigService());

        static::expectExceptionObject(new DecorationPatternException(SiteSettingsRoute::class));

        $route->getDecorated();
    }
}
