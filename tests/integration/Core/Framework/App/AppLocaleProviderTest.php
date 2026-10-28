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
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class AppLocaleProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AppLocaleProvider $localeProvider;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->localeProvider = static::getContainer()->get(AppLocaleProvider::class);
        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testGetLocaleWithSystemSource(): void
    {
        $locale = $this->localeProvider->getLocaleFromContext(Context::createDefaultContext());

        static::assertSame('en-GB', $locale);
    }

    public function testGetLocaleWithSalesChannelSource(): void
    {
        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $locale = $this->localeProvider->getLocaleFromContext($context->getContext());

        static::assertSame('en-GB', $locale);
    }

    public function testGetLocaleFromContextReturnsLocaleFromUser(): void
    {
        $userId = Uuid::randomHex();
        $userLocale = 'de-US';

        $this->userRepository->create([[
            'id' => $userId,
            'username' => 'testUser',
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'first@last.de',
            'password' => 'contena',
            'locale' => [
                'code' => $userLocale,
                'name' => 'testLocale',
                'territory' => 'somewhere',
            ],
        ]], Context::createDefaultContext());

        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $locale = $this->localeProvider->getLocaleFromContext($context);

        static::assertSame($userLocale, $locale);
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
