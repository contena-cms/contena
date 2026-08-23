<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
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
     * @param array<string, string|array<string, bool>|null> $overrideOptions
     *
     * @throws Exception
     */
    public function restoreByMember(string $memberId, Context $context, array $overrideOptions = []): ChannelContext
    {
        $member = $this->connection->createQueryBuilder()
            ->select(
                'LOWER(HEX(language_id))',
                'LOWER(HEX(member_group_id))',
                'LOWER(HEX(channel_id))',
            )
            ->from('member')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($memberId))
            ->executeQuery()
            ->fetchAssociative();

        if (!$member) {
            throw ChannelException::memberNotFoundById($memberId);
        }

        [$languageId, $groupId, $channelId] = array_values($member);
        $options = [
            ChannelContextService::LANGUAGE_ID => $languageId,
            ChannelContextService::MEMBER_ID => $memberId,
            ChannelContextService::MEMBER_GROUP_ID => $groupId,
            ChannelContextService::VERSION_ID => $context->getVersionId(),
        ];

        $channelContext = $this->factory->create(
            Uuid::randomHex(),
            $channelId,
            array_merge($options, $overrideOptions),
        );

        $channelContext->getContext()->addState(...$context->getStates());

        return $channelContext;
    }
}
