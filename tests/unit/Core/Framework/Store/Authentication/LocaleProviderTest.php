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

        $admin_userLocale = new LocaleEntity();
        $admin_userLocale->setCode('admin_user-locale');

        $admin_user = new AdminUserEntity();
        $admin_user->setUniqueIdentifier('admin_user-identifier');
        $admin_user->setLocale($admin_userLocale);

        $admin_userRepository = static::createMock(EntityRepository::class);
        $admin_userRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                AdminUserDefinition::ENTITY_NAME,
                1,
                new AdminUserCollection([$admin_user]),
                null,
                new Criteria(),
                $context
            ));

        $provider = new LocaleProvider($admin_userRepository);

        static::assertSame('admin_user-locale', $provider->getLocaleFromContext($context));
    }

    public function testGetLocaleFromContextThrowsIfAssociatedUserCanNotBeFound(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('admin_user-id', null));

        $admin_userRepository = static::createMock(EntityRepository::class);
        $admin_userRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                AdminUserDefinition::ENTITY_NAME,
                1,
                new AdminUserCollection([]),
                null,
                new Criteria(),
                $context
            ));

        $provider = new LocaleProvider($admin_userRepository);

        static::expectException(EntityNotFoundException::class);
        $provider->getLocaleFromContext($context);
    }
}
