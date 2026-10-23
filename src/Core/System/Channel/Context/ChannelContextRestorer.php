<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;

class ChannelContextRestorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractChannelContextFactory $factory,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param array<string> $overrideOptions
     *
     * @throws ChannelException
     */
    public function restoreByUser(string $userId, Context $context, array $overrideOptions = []): ChannelContext
    {
        $user = $this->connection->createQueryBuilder()
            ->select(
                'LOWER(HEX(language_id))',
                'LOWER(HEX(user_group_id))',
                'LOWER(HEX(channel_id))',
            )
            ->from('user')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($userId))
            ->executeQuery()
            ->fetchAssociative();

        if (!$user) {
            throw ChannelException::userNotFoundByIdException($userId);
        }

        [$languageId, $groupId, $channelId] = array_values($user);
        $options = [
            ChannelContextService::LANGUAGE_ID => $languageId,
            ChannelContextService::CUSTOMER_ID => $userId,
            ChannelContextService::CUSTOMER_GROUP_ID => $groupId,
            ChannelContextService::VERSION_ID => $context->getVersionId(),
        ];

        $options = array_merge($options, $overrideOptions);

        $token = Uuid::randomHex();
        $channelContext = $this->factory->create(
            $token,
            $channelId,
            $options
        );

        $channelContext->getContext()->addState(...$context->getStates());

        return $channelContext;
    }
}
