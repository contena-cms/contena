<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Processor;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class AssociationLoadingListingProcessor extends AbstractListingProcessor
{
    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addAssociation('cover.media');
    }
}
