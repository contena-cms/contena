<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Maintenance\Channel\Command\ChannelUpdateDomainCommand;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ChannelUpdateDomainCommandTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<ChannelDomainCollection>
     */
    private EntityRepository $domainRepo;

    protected function setUp(): void
    {
        $this->domainRepo = static::getContainer()->get('channel_domain.repository');
        $criteria = $this->getFrontendDomainCriteria();

        $domain = $this->domainRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if ($domain === null) {
            static::markTestSkipped('ChannelUpdateDomainCommandTests need frontend channel to be active');
        }
    }

    public function testUpdateDomainCommand(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(ChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de']);

        static::assertSame(
            Command::SUCCESS,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:update:domain\" returned errors:\n" . $commandTester->getDisplay()
        );

        $criteria = $this->getFrontendDomainCriteria();

        $domain = $this->domainRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($domain);

        static::assertSame('test.de', parse_url($domain->getUrl(), \PHP_URL_HOST));
    }

    public function testUpdateWithRandomPreviousDomain(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(ChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de', '--previous-domain' => 'shop.test']);

        static::assertSame(
            Command::SUCCESS,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:update:domain\" returned errors:\n" . $commandTester->getDisplay()
        );

        $criteria = $this->getFrontendDomainCriteria();

        $domain = $this->domainRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($domain);

        $defaultDomain = parse_url((string) EnvironmentHelper::getVariable('APP_URL'), \PHP_URL_HOST);
        static::assertSame($defaultDomain, parse_url($domain->getUrl(), \PHP_URL_HOST));
    }

    public function testUpdateWithCorrectPreviousDomain(): void
    {
        $defaultHost = parse_url((string) EnvironmentHelper::getVariable('APP_URL'), \PHP_URL_HOST);

        $commandTester = new CommandTester(static::getContainer()->get(ChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de', '--previous-domain' => $defaultHost]);

        static::assertSame(
            Command::SUCCESS,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:update:domain\" returned errors:\n" . $commandTester->getDisplay()
        );

        $criteria = $this->getFrontendDomainCriteria();

        $domain = $this->domainRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($domain);

        static::assertSame('test.de', parse_url($domain->getUrl(), \PHP_URL_HOST));
    }

    private function getFrontendDomainCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('channel.typeId', Defaults::CHANNEL_TYPE_FRONTEND));

        return $criteria;
    }
}
