<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
class MediaExtension extends AbstractExtension
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(private readonly EntityRepository $mediaRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('searchMedia', $this->searchMedia(...)),
        ];
    }

    /**
     * @param array<string> $ids
     */
    public function searchMedia(array $ids, Context $context): MediaCollection
    {
        if ($ids === []) {
            return new MediaCollection();
        }

        $criteria = new Criteria($ids);

        return $this->mediaRepository->search($criteria, $context)->getEntities();
    }
}
