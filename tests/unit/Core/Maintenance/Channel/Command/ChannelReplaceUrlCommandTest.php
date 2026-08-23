<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Command\ChannelReplaceUrlCommand;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ChannelReplaceUrlCommand::class)]
class ChannelReplaceUrlCommandTest extends TestCase
{
    public function testExecuteSuccessfullyReplacesUrl(): void
    {
        $domainId = Uuid::randomHex();
        $previousUrl = 'https://old-domain.com';
        $newUrl = 'https://new-domain.com';
        $domainEntity = new ChannelDomainEntity();
        $domainEntity->setId($domainId);
        $domainEntity->setUrl($previousUrl);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new ChannelDomainCollection([$domainEntity]));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria) use ($previousUrl): bool {
                    $filters = $criteria->getFilters();
                    static::assertCount(1, $filters);
                    static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                    static::assertSame('url', $filters[0]->getField());
                    static::assertSame($previousUrl, $filters[0]->getValue());
                    static::assertSame(1, $criteria->getLimit());

                    return true;
                }),
                static::isInstanceOf(Context::class)
            )
            ->willReturn($searchResult);
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $domainId, 'url' => $newUrl]], static::isInstanceOf(Context::class));

        $commandTester = new CommandTester(new ChannelReplaceUrlCommand($repository));
        $commandTester->execute(['previous-url' => $previousUrl, 'new-url' => $newUrl]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testExecuteFailsWhenDomainNotFound(): void
    {
        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new ChannelDomainCollection());

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('search')->willReturn($searchResult);
        $repository->expects($this->never())->method('update');

        $commandTester = new CommandTester(new ChannelReplaceUrlCommand($repository));
        $commandTester->execute([
            'previous-url' => 'https://non-existent-domain.com',
            'new-url' => 'https://new-domain.com',
        ]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    /**
     * @param array{previous-url: string, new-url: string} $arguments
     */
    #[DataProvider('invalidUrlDataProvider')]
    public function testExecuteFailsWithInvalidUrls(array $arguments): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('search');
        $repository->expects($this->never())->method('update');

        $commandTester = new CommandTester(new ChannelReplaceUrlCommand($repository));
        $commandTester->execute($arguments);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    /**
     * @return \Generator<string, array{arguments: array{previous-url: string, new-url: string}}>
     */
    public static function invalidUrlDataProvider(): \Generator
    {
        yield 'empty previous URL' => [
            'arguments' => ['previous-url' => '', 'new-url' => 'https://new-domain.com'],
        ];
        yield 'whitespace-only previous URL' => [
            'arguments' => ['previous-url' => '   ', 'new-url' => 'https://new-domain.com'],
        ];
        yield 'invalid new URL' => [
            'arguments' => ['previous-url' => 'https://old-domain.com', 'new-url' => 'not-a-valid-url'],
        ];
        yield 'identical URLs' => [
            'arguments' => ['previous-url' => 'https://same-domain.com', 'new-url' => 'https://same-domain.com'],
        ];
    }
}
