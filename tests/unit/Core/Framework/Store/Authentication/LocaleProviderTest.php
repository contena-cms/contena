<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Store\Authentication\LocaleProvider;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\Locale\LocaleEntity;

/**
 * @internal
 */
#[CoversClass(LocaleProvider::class)]
class LocaleProviderTest extends TestCase
{
    public function testGetLocaleFromContextReturnsEnGbInSystemSource(): void
    {
        $provider = new LocaleProvider(static::createStub(EntityRepository::class));

        static::assertSame('en-GB', $provider->getLocaleFromContext(Context::createDefaultContext()));
    }

    public function testGetLocaleFromContextReturnsEnGbIfNoUserIsAssociated(): void
    {
        $provider = new LocaleProvider(static::createStub(EntityRepository::class));

        static::assertSame(
            'en-GB',
            $provider->getLocaleFromContext(Context::createDefaultContext(
                new AdminApiSource(null, 'i-am-an-integration')
            ))
        );
    }

    public function testGetLocaleFromContextReturnsLocaleFromUser(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('admin_user-id', null));

        $adminUserLocale = new LocaleEntity();
        $adminUserLocale->setCode('admin_user-locale');

        $adminUser = new AdminUserEntity();
        $adminUser->setUniqueIdentifier('admin_user-identifier');
        $adminUser->setLocale($adminUserLocale);

        $adminUserRepository = static::createMock(EntityRepository::class);
        $adminUserRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                AdminUserDefinition::ENTITY_NAME,
                1,
                new AdminUserCollection([$adminUser]),
                null,
                new Criteria(),
                $context
            ));

        $provider = new LocaleProvider($adminUserRepository);

        static::assertSame('admin_user-locale', $provider->getLocaleFromContext($context));
    }

    public function testGetLocaleFromContextThrowsIfAssociatedUserCanNotBeFound(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('admin_user-id', null));

        $adminUserRepository = static::createMock(EntityRepository::class);
        $adminUserRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                AdminUserDefinition::ENTITY_NAME,
                1,
                new AdminUserCollection([]),
                null,
                new Criteria(),
                $context
            ));

        $provider = new LocaleProvider($adminUserRepository);

        static::expectException(EntityNotFoundException::class);
        $provider->getLocaleFromContext($context);
    }
}
