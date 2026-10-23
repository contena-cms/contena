<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Contena\Core\System\User\Service\BlogReviewCountService;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataException;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class BlogReviewGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly BlogReviewDefinition $blogReviewDefinition,
        private readonly Connection $connection,
        private readonly BlogReviewCountService $blogReviewCountService
    ) {
    }

    public function getDefinition(): string
    {
        return BlogReviewDefinition::class;
    }

    /**
     * @param array<mixed> $options
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $userIds = $this->getUserIds();
        $blogIds = $this->getBlogIds();
        $channelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM channel');
        if ($channelIds === []) {
            throw DemodataException::wrongExecutionOrder();
        }
        $points = [1, 2, 3, 4, 5];

        $payload = [];

        $writeContext = WriteContext::createFromContext($context->getContext());

        $userIdsWithReviews = [];

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $userId = $context->getFaker()->randomElement($userIds);
            \assert(\is_string($userId));
            $userIdsWithReviews[$userId] = true;

            $payload[] = [
                'id' => Uuid::randomHex(),
                'blogId' => $context->getFaker()->randomElement($blogIds),
                'userId' => $userId,
                'channelId' => $channelIds[array_rand($channelIds)],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'externalUser' => $context->getFaker()->name(),
                'externalEmail' => $context->getFaker()->email(),
                'title' => $context->getFaker()->sentence(),
                'content' => $context->getFaker()->text(),
                'points' => $context->getFaker()->randomElement($points),
                'status' => (bool) random_int(0, 1),
                'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            ];

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->blogReviewDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if ($payload !== []) {
            $this->writer->upsert($this->blogReviewDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        foreach ($userIdsWithReviews as $userId => $_) {
            $this->blogReviewCountService->updateReviewCountForUser(Uuid::fromHexToBytes($userId));
        }

        $context->getConsole()->progressFinish();
    }

    /**
     * @return array<string>
     */
    private function getUserIds(): array
    {
        $sql = 'SELECT LOWER(HEX(id)) as id FROM user LIMIT 200';

        $userIds = $this->connection->fetchAllAssociative($sql);

        return array_column($userIds, 'id');
    }

    /**
     * @return array<string>
     */
    private function getBlogIds(): array
    {
        $sql = 'SELECT LOWER(HEX(id)) as id FROM blog WHERE version_id = :liveVersionId LIMIT 200';

        $blogIds = $this->connection->fetchAllAssociative($sql, ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]);

        return array_column($blogIds, 'id');
    }
}
