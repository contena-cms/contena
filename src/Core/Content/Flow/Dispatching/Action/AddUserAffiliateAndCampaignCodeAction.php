<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserCollection;

/**
 * @internal
 */
class AddUserAffiliateAndCampaignCodeAction extends FlowAction implements DelayableAction
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $userRepository
    ) {
    }

    public static function getName(): string
    {
        return 'action.add.user.affiliate.and.campaign.code';
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [UserAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(UserAware::CUSTOMER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(UserAware::CUSTOMER_ID));
    }

    /**
     * @return array<mixed>
     */
    private function getAffiliateAndCampaignCodeFromUserId(string $userId): array
    {
        $data = $this->connection->fetchAssociative(
            'SELECT affiliate_code, campaign_code FROM user WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($userId),
            ]
        );

        if (!$data) {
            return [];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $userId): void
    {
        if (!\array_key_exists('affiliateCode', $config) || !\array_key_exists('campaignCode', $config)) {
            return;
        }

        $userData = $this->getAffiliateAndCampaignCodeFromUserId($userId);
        if ($userData === []) {
            return;
        }

        $affiliateCode = $userData['affiliate_code'];
        if ($affiliateCode === null || $config['affiliateCode']['upsert']) {
            $affiliateCode = $config['affiliateCode']['value'];
        }

        $campaignCode = $userData['campaign_code'];
        if ($campaignCode === null || $config['campaignCode']['upsert']) {
            $campaignCode = $config['campaignCode']['value'];
        }

        $data = [];
        if ($affiliateCode !== $userData['affiliate_code']) {
            $data['affiliateCode'] = $affiliateCode;
        }

        if ($campaignCode !== $userData['campaign_code']) {
            $data['campaignCode'] = $campaignCode;
        }

        if ($data === []) {
            return;
        }

        $data['id'] = $userId;

        $this->userRepository->update([$data], $context);
    }
}
