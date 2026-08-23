<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\TenantIsolationTestTrait;

/**
 * @internal
 */
class MemberTenantScopeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TenantIsolationTestTrait;

    public function testMembersAndTheirNestedAddressesAreIsolatedByTenant(): void
    {
        $tenantA = $this->seedTenant('member-a');
        $tenantB = $this->seedTenant('member-b');
        $email = 'tenant-scope-member-' . \bin2hex(\random_bytes(4)) . '@example.com';
        $channel = $this->createTenantChannel($tenantA);

        $this->assertTenantIsolated(
            $tenantA,
            $tenantB,
            fn (string $tenantId): mixed => $this->memberRepository()->create([[
                'id' => Uuid::randomHex(),
                'groupId' => $channel['memberGroupId'],
                'channelId' => $channel['channelId'],
                'languageId' => $channel['languageId'],
                'memberNumber' => 'tenant-scope-' . \bin2hex(\random_bytes(4)),
                'name' => 'Tenant Scope Member',
                'email' => $email,
                'addresses' => [[
                    'id' => Uuid::randomHex(),
                    'countryId' => $this->defaultCountryId(),
                    'firstName' => 'Tenant',
                    'lastName' => 'Scope',
                    'city' => 'Test City',
                    'street' => 'Test Street 1',
                ]],
            ]], Context::createTenantContext($tenantId)),
            function (Context $context) use ($email): int {
                $member = $this->memberRepository()->search(
                    new Criteria()->addFilter(new EqualsFilter('email', $email))->setLimit(1),
                    $context,
                )->getEntities()->first();

                return $member instanceof MemberEntity ? 1 : 0;
            },
        );

        // The nested address inherited the tenant of the member write.
        $member = $this->memberRepository()->search(
            new Criteria()->addFilter(new EqualsFilter('email', $email))->addAssociation('addresses')->setLimit(1),
            Context::createGlobalContext(),
        )->getEntities()->first();
        static::assertInstanceOf(MemberEntity::class, $member);
        static::assertNotNull($member->getAddresses());

        $address = $member->getAddresses()->first();
        static::assertInstanceOf(MemberAddressEntity::class, $address);
        static::assertSame($tenantA, $address->getTenantId());
    }

    /**
     * @return array{channelId: string, languageId: string, memberGroupId: string}
     */
    private function createTenantChannel(string $tenantId): array
    {
        $default = static::getContainer()->get('channel.repository')->search(
            new Criteria([TestDefaults::CHANNEL]),
            Context::createDefaultContext(),
        )->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $default);

        $channelId = Uuid::randomHex();
        $languageId = $default->getLanguageId();
        $context = Context::createTenantContext($tenantId);
        $memberGroupId = Uuid::randomHex();
        $this->memberGroupRepository()->create([[
            'id' => $memberGroupId,
            'name' => 'Tenant member scope group',
        ]], $context);
        $navigationCategoryId = Uuid::randomHex();
        $this->categoryRepository()->create([[
            'id' => $navigationCategoryId,
            'name' => 'Tenant member scope navigation',
        ]], $context);

        static::getContainer()->get('channel.repository')->create([[
            'id' => $channelId,
            'name' => 'Tenant member scope channel',
            'accessKey' => 'tenant-member-scope-' . \bin2hex(\random_bytes(4)),
            'typeId' => $default->getTypeId(),
            'languageId' => $languageId,
            'countryId' => $default->getCountryId(),
            'memberGroupId' => $memberGroupId,
            'navigationCategoryId' => $navigationCategoryId,
            'navigationCategoryVersionId' => $default->getNavigationCategoryVersionId(),
            'languages' => [['id' => $languageId]],
            'countries' => [['id' => $default->getCountryId()]],
        ]], $context);

        return ['channelId' => $channelId, 'languageId' => $languageId, 'memberGroupId' => $memberGroupId];
    }

    private function defaultCountryId(): string
    {
        $country = $this->countryRepository()->search(
            new Criteria()->addFilter(new EqualsFilter('active', true))->setLimit(1),
            Context::createDefaultContext(),
        )->getEntities()->first();
        static::assertInstanceOf(CountryEntity::class, $country);

        return $country->getId();
    }

    /**
     * @return EntityRepository<MemberCollection>
     */
    private function memberRepository(): EntityRepository
    {
        return static::getContainer()->get('member.repository');
    }

    /**
     * @return EntityRepository<CountryCollection>
     */
    private function countryRepository(): EntityRepository
    {
        return static::getContainer()->get('country.repository');
    }

    /**
     * @return EntityRepository<CategoryCollection>
     */
    private function categoryRepository(): EntityRepository
    {
        return static::getContainer()->get('category.repository');
    }

    /**
     * @return EntityRepository<MemberGroupCollection>
     */
    private function memberGroupRepository(): EntityRepository
    {
        return static::getContainer()->get('member_group.repository');
    }
}
