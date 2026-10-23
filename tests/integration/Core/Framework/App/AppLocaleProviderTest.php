<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class AppLocaleProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AppLocaleProvider $localeProvider;

    /**
     * @var EntityRepository<AdminUserCollection>
     */
    private EntityRepository $admin_userRepository;

    protected function setUp(): void
    {
        $this->localeProvider = static::getContainer()->get(AppLocaleProvider::class);
        $this->admin_userRepository = static::getContainer()->get('admin_user.repository');
    }

    public function testGetLocaleWithSystemSource(): void
    {
        $locale = $this->localeProvider->getLocaleFromContext(Context::createDefaultContext());

        static::assertSame('en-GB', $locale);
    }

    public function testGetLocaleWithChannelSource(): void
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $locale = $this->localeProvider->getLocaleFromContext($context->getContext());

        static::assertSame('en-GB', $locale);
    }

    public function testGetLocaleFromContextReturnsLocaleFromUser(): void
    {
        $admin_userId = Uuid::randomHex();
        $admin_userLocale = 'de-US';

        $this->admin_userRepository->create([[
            'id' => $admin_userId,
            'username' => 'testUser',
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'first@last.de',
            'password' => 'contena',
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
