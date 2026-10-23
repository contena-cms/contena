<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Traits;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Integration\Helper\MailEventListener;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
trait TestShortHands
{
    use KernelTestBehaviour;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

    /**
     * @param array<string, mixed> $options
     */
    protected function getContext(?string $token = null, array $options = [], string $channelId = TestDefaults::CHANNEL): ChannelContext
    {
        $token ??= Uuid::randomHex();

        return static::getContainer()->get(ChannelContextFactory::class)
            ->create($token, $channelId, $options);
    }







    protected function login(ChannelContext $context, ?string $userId = null): ChannelContext
    {
        if ($userId === null) {
            $user = new UserBuilder(
                new IdsCollection(),
                Uuid::randomHex(),
                $context->getChannelId()
            );

            static::getContainer()->get('user.repository')->create(
                [$user->build()],
                Context::createDefaultContext()
            );

            $userId = $user->id;
        }

        return $this->getContext($context->getToken(), [
            ChannelContextService::CUSTOMER_ID => $userId,
        ], $context->getChannelId());
    }

    protected function assertMailSent(MailEventListener $listener, string $type): void
    {
        static::assertTrue($listener->sent($type), \sprintf('Mail with type %s was not sent', $type));
    }

    protected function mailListener(\Closure $closure): mixed
    {
        $mapping = static::getContainer()->get(Connection::class)
            ->fetchAllKeyValue('SELECT LOWER(HEX(id)), technical_name FROM mail_template_type');

        $listener = new MailEventListener($mapping);

        EventHookDispatcher::fromContainer(static::getContainer())->on(FlowSendMailActionEvent::class, $listener);

        $result = $closure($listener);

        EventHookDispatcher::fromContainer(static::getContainer())->off(FlowSendMailActionEvent::class, $listener);

        return $result;
    }

    private function assertStock(string $blogId, int $stock, int $available): void
    {
        /** @var array{stock: string, available_stock: string}|false $stocks */
        $stocks = static::getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT stock, available_stock FROM blog WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($blogId)]
        );

        static::assertNotFalse($stocks, \sprintf('Blog with id %s not found', $blogId));

        static::assertSame($stock, (int) $stocks['stock'], \sprintf('Blog with id %s has wrong stock', $blogId));

        static::assertSame($available, (int) $stocks['available_stock'], \sprintf('Blog with id %s has wrong available stock', $blogId));
    }
}
