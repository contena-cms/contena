<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;

abstract class FileNameProvider
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(private readonly EntityRepository $mediaRepository)
    {
    }

    public function provide(
        string $preferredFileName,
        string $fileExtension,
        ?string $mediaId,
        Context $context
    ): string {
        $mediaWithRelatedFilename = $this->finderOtherMediaWithFileName(
            $preferredFileName,
            $fileExtension,
            $mediaId,
            $context
        );

        return $this->getPossibleFileName($mediaWithRelatedFilename, $preferredFileName);
    }

    abstract protected function getNextFileName(
        string $originalFileName,
        MediaCollection $relatedMedia,
        int $iteration
    ): string;

    private function finderOtherMediaWithFileName(
        string $fileName,
        string $fileExtension,
        ?string $mediaId,
        Context $context
    ): MediaCollection {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new ContainsFilter('fileName', $fileName),
                new EqualsFilter('fileExtension', $fileExtension),
                new NotEqualsFilter('id', $mediaId),
            ]
        ));

        return $this->mediaRepository->search($criteria, $context)->getEntities();
    }

    private function getPossibleFileName(
        MediaCollection $relatedMedia,
        string $preferredFileName,
        int $iteration = 0
    ): string {
        $nextFileName = $this->getNextFileName($preferredFileName, $relatedMedia, $iteration);

        foreach ($relatedMedia as $media) {
            if ($media->hasFile() && $media->getFileName() === $nextFileName) {
                return $this->getPossibleFileName($relatedMedia, $preferredFileName, $iteration + 1);
            }
        }

        return $nextFileName;
    }
}
