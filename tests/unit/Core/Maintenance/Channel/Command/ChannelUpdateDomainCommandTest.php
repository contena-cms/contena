<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Maintenance\Channel\Command\ChannelUpdateDomainCommand;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ChannelUpdateDomainCommand::class)]
class ChannelUpdateDomainCommandTest extends TestCase
{
    public function testUpdatesMatchingDomainAndPreservesUrlParts(): void
    {
        $matching = new ChannelDomainEntity();
        $matching->setId('domain-1');
        $matching->setUrl('https://old.example.test:8443/path?query=value#fragment');

        $different = new ChannelDomainEntity();
        $different->setId('domain-2');
        $different->setUrl('https://other.example.test/path');

        $repository = StaticEntityRepository::of(
            ChannelDomainCollection::class,
            [new ChannelDomainCollection([$matching, $different])],
            new ChannelDomainDefinition()
        );
        $commandTester = new CommandTester(new ChannelUpdateDomainCommand($repository));

        $commandTester->execute([
            'domain' => 'new.example.test',
            '--previous-domain' => 'old.example.test',
        ]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertSame([[
            'id' => 'domain-1',
            'url' => 'https://new.example.test:8443/path?query=value#fragment',
        ]], $repository->updates[0]);
    }
}
