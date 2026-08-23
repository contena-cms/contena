<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Message;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\Thumbnail\ThumbnailService;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class GenerateThumbnailsHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private ThumbnailService $thumbnailService,
        private EntityRepository $mediaRepository,
        private LoggerInterface $logger,
        private bool $remoteThumbnailsEnable = false
    ) {
    }

    public function __invoke(GenerateThumbnailsMessage|UpdateThumbnailsMessage $msg): void
    {
        if ($this->remoteThumbnailsEnable) {
            return;
        }

        $context = $msg->getContext();

        $criteria = new Criteria();
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');
        $criteria->addFilter(new EqualsAnyFilter('media.id', $msg->getMediaIds()));

        $entities = $this->mediaRepository->search($criteria, $context)->getEntities();

        if ($msg instanceof UpdateThumbnailsMessage) {
            foreach ($entities as $media) {
                try {
                    $this->thumbnailService->updateThumbnails($media, $context, $msg->isStrict(), $msg->isForce());
                } catch (\Throwable $e) {
                    $this->logger->error('Thumbnail generation failed for media {mediaId}', [
                        'mediaId' => $media->getId(),
                        'exception' => $e,
                    ]);
                }
            }
        } else {
            $this->thumbnailService->generate($entities, $context);
        }
    }
}
