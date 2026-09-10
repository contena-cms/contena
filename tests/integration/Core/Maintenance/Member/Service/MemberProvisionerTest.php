<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Maintenance\Member\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Member\Service\MemberProvisioner;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MemberProvisionerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testProvisionCreatesPlatformMemberForDefaultWebChannel(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'member-password';

        $memberProvisioner = static::getContainer()->get(MemberProvisioner::class);
        $memberProvisioner->provision($email, $password, 'Initial administrator');

        $criteria = new Criteria()->addFilter(new EqualsFilter('email', $email));
        $member = $this->memberRepository()->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(MemberEntity::class, $member);
        static::assertSame('Initial administrator', $member->getName());
        static::assertSame($email, $member->getEmail());
        static::assertTrue($member->getActive());
        static::assertTrue(password_verify($password, $member->getPassword() ?? ''));

        $connection = static::getContainer()->get(Connection::class);
        $channel = $connection->fetchAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`member_group_id`)) AS `member_group_id`, LOWER(HEX(`language_id`)) AS `language_id`
             FROM `channel`
             WHERE `id` = :channelId',
            ['channelId' => Uuid::fromHexToBytes($member->getChannelId())],
        );

        static::assertIsArray($channel);
        static::assertSame($channel['member_group_id'], $member->getGroupId());
        static::assertSame($channel['language_id'], $member->getLanguageId());
        static::assertNull($connection->fetchOne(
            'SELECT LOWER(HEX(`tenant_id`)) FROM `member` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($member->getId())],
        ) ?: null);

        $targetTenantContext = $this->createTenantContext($this->createTenant('Provisioned member target tenant'));
        $otherTenantContext = $this->createTenantContext($this->createTenant('Provisioned member other tenant'));

        static::assertNull($this->memberRepository()->search($criteria, $targetTenantContext)->getEntities()->first());
        static::assertNull($this->memberRepository()->search($criteria, $otherTenantContext)->getEntities()->first());
        static::assertInstanceOf(
            MemberEntity::class,
            $this->memberRepository()->search($criteria, Context::createGlobalContext())->getEntities()->first(),
        );
    }

    /**
     * @return EntityRepository<MemberCollection>
     */
    private function memberRepository(): EntityRepository
    {
        return static::getContainer()->get('member.repository');
    }
}
