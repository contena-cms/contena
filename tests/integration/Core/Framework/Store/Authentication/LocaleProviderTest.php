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
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class LocaleProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<AdminUserCollection>
     */
    private EntityRepository $adminUserRepository;

    private LocaleProvider $localeProvider;

    protected function setUp(): void
    {
        $this->admin_userRepository = static::getContainer()->get('admin_user.repository');
        $this->localeProvider = static::getContainer()->get(LocaleProvider::class);
    }

    public function testGetLocaleFromContextReturnsLocaleFromUser(): void
    {
        $adminUserId = Uuid::randomHex();
        $adminUserLocale = 'de-DE-1';

        $this->admin_userRepository->create([[
            'id' => $adminUserId,
            'username' => 'testUser',
            'name' => 'first',
            'lastName' => 'last',
            'email' => 'first@last.de',
            'password' => TestDefaults::HASHED_PASSWORD,
            'locale' => [
                'code' => $adminUserLocale,
                'name' => 'testLocale',
                'territory' => 'somewhere',
            ],
        ]], Context::createDefaultContext());

        $context = Context::createDefaultContext(new AdminApiSource($adminUserId));

        $locale = $this->localeProvider->getLocaleFromContext($context);

        static::assertSame($adminUserLocale, $locale);
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
