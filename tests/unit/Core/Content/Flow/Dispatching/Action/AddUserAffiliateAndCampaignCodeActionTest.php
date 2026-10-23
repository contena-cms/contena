<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Content\Flow\Dispatching\Action\AddUserAffiliateAndCampaignCodeAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AddUserAffiliateAndCampaignCodeAction::class)]
class AddUserAffiliateAndCampaignCodeActionTest extends TestCase
{
    private Connection&Stub $connection;

    /**
     * @var Stub&EntityRepository<UserCollection>
     */
    private Stub&EntityRepository $repository;

    private AddUserAffiliateAndCampaignCodeAction $action;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->repository = static::createStub(EntityRepository::class);
        $this->action = new AddUserAffiliateAndCampaignCodeAction($this->connection, $this->repository);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [UserAware::class],
            $this->action->requirements()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.add.user.affiliate.and.campaign.code', AddUserAffiliateAndCampaignCodeAction::getName());
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $existedData
     * @param array<string, mixed> $expected
     */
    #[DataProvider('actionExecuteProvider')]
    public function testActionWithExpectedUpdate(array $config, array $existedData, array $expected): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn($existedData);

        $userId = Uuid::randomHex();
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            UserAware::CUSTOMER_ID => $userId,
        ]);
        $flow->setConfig($config);

        $expected['id'] = $userId;
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('update')->with([$expected]);

        $this->createAction($connection, $repository)->handleFlow($flow);
    }

    public function testActionWithNotAware(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext());

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->createAction(repository: $repository)->handleFlow($flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            UserAware::CUSTOMER_ID => Uuid::randomHex(),
        ]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->createAction(repository: $repository)->handleFlow($flow);
    }

    public static function actionExecuteProvider(): \Generator
    {
        yield 'Test with non data exists' => [
            [
                'affiliateCode' => ['value' => '11111', 'upsert' => false],
                'campaignCode' => ['value' => '22222', 'upsert' => false],
            ],
            [
                'affiliate_code' => null,
                'campaign_code' => null,
            ],
            ['affiliateCode' => '11111', 'campaignCode' => '22222'],
        ];

        yield 'Test with data config only upsert campaignCode' => [
            [
                'affiliateCode' => ['value' => '33333', 'upsert' => false],
                'campaignCode' => ['value' => '33333', 'upsert' => true],
            ],
            ['affiliate_code' => '11111', 'campaign_code' => '22222'],
            ['campaignCode' => '33333'],
        ];

        yield 'Test with data config upsert both campaignCode and affiliateCode' => [
            [
                'affiliateCode' => ['value' => '33333', 'upsert' => true],
                'campaignCode' => ['value' => '33333', 'upsert' => true],
            ],
            ['affiliate_code' => '11111', 'campaign_code' => '22222'],
            ['affiliateCode' => '33333', 'campaignCode' => '33333'],
        ];
    }

    /**
     * @param EntityRepository<UserCollection>|null $repository
     */
    private function createAction(?Connection $connection = null, ?EntityRepository $repository = null): AddUserAffiliateAndCampaignCodeAction
    {
        return new AddUserAffiliateAndCampaignCodeAction(
            $connection ?? $this->connection,
            $repository ?? $this->repository,
        );
    }
}
