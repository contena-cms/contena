<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\Entity\DefinitionRegistryChain;
use Contena\Core\System\Channel\Exception\ChannelRepositoryNotFoundException;

/**
 * @internal
 */
#[CoversClass(DefinitionRegistryChain::class)]
class DefinitionRegistryChainTest extends TestCase
{
    private DefinitionInstanceRegistry&MockObject $definitionInstanceRegistry;

    private ChannelDefinitionInstanceRegistry&MockObject $channelDefinitionInstanceRegistry;

    private DefinitionRegistryChain $definitionRegistryChain;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->channelDefinitionInstanceRegistry = $this->createMock(ChannelDefinitionInstanceRegistry::class);
        $this->definitionRegistryChain = new DefinitionRegistryChain(
            $this->definitionInstanceRegistry,
            $this->channelDefinitionInstanceRegistry
        );
    }

    public function testGetRepository(): void
    {
        $this->channelDefinitionInstanceRegistry
            ->expects($this->once())
            ->method('getChannelRepository')
            ->willThrowException(new ChannelRepositoryNotFoundException('media'));

        $this->definitionInstanceRegistry
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn(static::createStub(EntityRepository::class));

        $repository = $this->definitionRegistryChain->getRepository('media');

        static::assertInstanceOf(EntityRepository::class, $repository);
    }

    public function testGetChannelRepository(): void
    {
        $this->channelDefinitionInstanceRegistry
            ->expects($this->once())
            ->method('getChannelRepository')
            ->willReturn(static::createStub(ChannelRepository::class));

        $this->definitionInstanceRegistry
            ->expects($this->never())
            ->method('getRepository');

        $repository = $this->definitionRegistryChain->getRepository('category');

        static::assertInstanceOf(ChannelRepository::class, $repository);
    }
}
