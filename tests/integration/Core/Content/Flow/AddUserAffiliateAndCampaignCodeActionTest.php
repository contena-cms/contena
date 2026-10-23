<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\Action\AddUserAffiliateAndCampaignCodeAction;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class AddUserAffiliateAndCampaignCodeActionTest extends TestCase
{
    use CacheTestBehaviour;
    use OrderActionTrait;

    /**
     * @var EntityRepository<FlowCollection>
     */
    private EntityRepository $flowRepository;

    protected function setUp(): void
    {
        $this->flowRepository = static::getContainer()->get('flow.repository');

        $this->userRepository = static::getContainer()->get('user.repository');

        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
    }

    /**
     * @param array<string, mixed> $existedData
     * @param array<string, mixed> $updateData
     * @param array<string, mixed> $expectData
     */
    #[DataProvider('createDataProvider')]
    public function testAddAffiliateAndCampaignCodeForUser(array $existedData, array $updateData, array $expectData): void
    {
        $email = 'thuy@gmail.com';
        $this->prepareUser($email, $existedData);

        $sequenceId = Uuid::randomHex();
        $this->flowRepository->create([[
            'name' => 'User login',
            'eventName' => UserLoginEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'sequences' => [
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => AddUserAffiliateAndCampaignCodeAction::getName(),
                    'position' => 1,
                    'config' => $updateData,
                ],
            ],
        ]], Context::createDefaultContext());

        $this->login($email, 'contena');

        static::assertNotNull($this->userRepository);
        /** @var UserEntity $user */
        $user = $this->userRepository->search(new Criteria([$this->ids->get('user')]), Context::createDefaultContext())->getEntities()->first();

        static::assertSame($user->getAffiliateCode(), $expectData['affiliateCode']);
        static::assertSame($user->getCampaignCode(), $expectData['campaignCode']);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function createDataProvider(): iterable
    {
        yield 'new entity stores affiliate and campaign codes' => [
            [],
            [
                'affiliateCode' => ['value' => '11111', 'upsert' => false],
                'campaignCode' => ['value' => '22222', 'upsert' => false],
            ],
            ['affiliateCode' => '11111', 'campaignCode' => '22222'],
        ];
        yield 'existing affiliate and campaign codes are kept when upsert is disabled' => [
            ['affiliateCode' => '11111', 'campaignCode' => '22222'],
            [
                'affiliateCode' => ['value' => '33333', 'upsert' => false],
                'campaignCode' => ['value' => '33333', 'upsert' => false],
            ],
            ['affiliateCode' => '11111', 'campaignCode' => '22222'],
        ];
        yield 'campaign code is updated when only campaign upsert is enabled' => [
            ['affiliateCode' => '11111', 'campaignCode' => '22222'],
            [
                'affiliateCode' => ['value' => '33333', 'upsert' => false],
                'campaignCode' => ['value' => '33333', 'upsert' => true],
            ],
            ['affiliateCode' => '11111', 'campaignCode' => '33333'],
        ];
        yield 'affiliate and campaign codes are updated when both upserts are enabled' => [
            ['affiliateCode' => '11111', 'campaignCode' => '22222'],
            [
                'affiliateCode' => ['value' => '33333', 'upsert' => true],
                'campaignCode' => ['value' => '33333', 'upsert' => true],
            ],
            ['affiliateCode' => '33333', 'campaignCode' => '33333'],
        ];
    }
}
