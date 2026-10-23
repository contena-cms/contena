<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Store\Authentication\LocaleProvider;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class LocaleProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<AdminUserCollection>
     */
    private EntityRepository $admin_userRepository;

    private LocaleProvider $localeProvider;

    protected function setUp(): void
    {
        $this->admin_userRepository = static::getContainer()->get('admin_user.repository');
        $this->localeProvider = static::getContainer()->get(LocaleProvider::class);
    }

    public function testGetLocaleFromContextReturnsLocaleFromUser(): void
    {
        $admin_userId = Uuid::randomHex();
        $admin_userLocale = 'de-DE-1';

        $this->admin_userRepository->create([[
            'id' => $admin_userId,
            'username' => 'testUser',
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'first@last.de',
            'password' => TestDefaults::HASHED_PASSWORD,
            'locale' => [
                'code' => $admin_userLocale,
                'name' => 'testLocale',
                'territory' => 'somewhere',
            ],
        ]], Context::createDefaultContext());

        $context = Context::createDefaultContext(new AdminApiSource($admin_userId));

        $locale = $this->localeProvider->getLocaleFromContext($context);

        static::assertSame($admin_userLocale, $locale);
    }

    public function testGetLocaleFromContextReturnsEnglishForSystemContext(): void
    {
        $locale = $this->localeProvider->getLocaleFromContext(Context::createDefaultContext());

        static::assertSame('en-GB', $locale);
    }

    public function testGetLocaleFromContextReturnsEnglishForIntegrations(): void
    {
        $locale = $this->localeProvider->getLocaleFromContext(
            Context::createDefaultContext(new AdminApiSource(null, Uuid::randomHex()))
        );

        static::assertSame('en-GB', $locale);
    }
}
