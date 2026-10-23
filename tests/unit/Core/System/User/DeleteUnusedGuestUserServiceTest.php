<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\DeleteUnusedGuestUserService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(DeleteUnusedGuestUserService::class)]
class DeleteUnusedGuestUserServiceTest extends TestCase
{
    public function testCountNoConfig(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService
            ->expects($this->once())
            ->method('getInt')
            ->willReturn(0);

        $service = new DeleteUnusedGuestUserService(
            static::createStub(EntityRepository::class),
            $configService
        );

        $result = $service->countUnusedUsers(Context::createDefaultContext());

        static::assertSame(0, $result);
    }

    public function testCountConfig(): void
    {
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection([new CountResult('user-count', 2)]));

        $configService = $this->createMock(SystemConfigService::class);
        $configService
            ->expects($this->once())
            ->method('getInt')
            ->willReturn(1);

        $service = new DeleteUnusedGuestUserService($userRepository, $configService);

        $result = $service->countUnusedUsers(Context::createDefaultContext());

        static::assertSame(2, $result);
    }

    public function testDeleteUser(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService
            ->expects($this->once())
            ->method('getInt')
            ->willReturn(1);

        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $deleteIds = \array_values(\array_map(static fn (string $id) => ['id' => $id], $ids));
        $searchResultIds = [];
        foreach ($ids as $id) {
            $searchResultIds[$id] = ['primaryKey' => $id, 'data' => []];
        }

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(3, $searchResultIds, new Criteria(), Context::createDefaultContext()));
        $userRepository
            ->expects($this->once())
            ->method('delete')
            ->with($deleteIds);

        $service = new DeleteUnusedGuestUserService(
            $userRepository,
            $configService
        );

        $result = $service->deleteUnusedUsers(Context::createDefaultContext());

        static::assertSame($deleteIds, $result);
    }
}
