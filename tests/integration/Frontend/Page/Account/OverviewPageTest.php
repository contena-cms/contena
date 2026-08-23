<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class OverviewPageTest extends TestCase
{
    use FrontendPageTestBehaviour;
    use IntegrationTestBehaviour;

    public function testItLoadsTheOverview(): void
    {
        $request = new Request();
        $context = $this->createChannelContext();
        $member = $this->createMember($context->getChannelId(), $context->getCurrentMemberGroup()->getId());
        $context->assign(['member' => $member]);

        $event = null;
        $this->catchEvent(AccountOverviewPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context, $member);

        static::assertSame($member->getId(), $page->getMember()->getId());
        self::assertPageEvent(AccountOverviewPageLoadedEvent::class, $event, $context, $request, $page);
    }

    protected function getPageLoader(): AccountOverviewPageLoader
    {
        return static::getContainer()->get(AccountOverviewPageLoader::class);
    }

    private function createMember(string $channelId, string $memberGroupId): MemberEntity
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository<MemberCollection> $repository */
        $repository = static::getContainer()->get('member.repository');
        $repository->create([[
            'id' => $id,
            'channelId' => $channelId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => $memberGroupId,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'password',
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ]], Context::createDefaultContext());

        $member = $repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(MemberEntity::class, $member);

        return $member;
    }
}
