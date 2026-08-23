<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Command\ChannelCreateCommand;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ChannelCreateCommand::class)]
class ChannelCreateCommandTest extends TestCase
{
    public function testExecuteSuccess(): void
    {
        $options = [
            '--id' => Uuid::randomHex(),
            '--typeId' => Uuid::randomHex(),
            '--name' => 'API',
            '--languageId' => Uuid::randomHex(),
            '--countryId' => Uuid::randomHex(),
            '--memberGroupId' => Uuid::randomHex(),
            '--navigationCategoryId' => Uuid::randomHex(),
        ];
        $accessKey = AccessKeyHelper::generateAccessKey('channel');

        $creator = $this->createMock(ChannelCreator::class);
        $creator->expects($this->once())
            ->method('createChannel')
            ->with(
                $options['--id'],
                $options['--name'],
                $options['--typeId'],
                $options['--languageId'],
                $options['--countryId'],
                $options['--memberGroupId'],
                $options['--navigationCategoryId'],
                null,
                null,
                []
            )
            ->willReturn($accessKey);

        $commandTester = new CommandTester(new ChannelCreateCommand($creator));

        static::assertSame(Command::SUCCESS, $commandTester->execute($options));
        static::assertStringContainsString('Channel has been created successfully.', $commandTester->getDisplay());
        static::assertStringContainsString($accessKey, $commandTester->getDisplay());
    }
}
