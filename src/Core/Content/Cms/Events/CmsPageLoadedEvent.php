<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Events;

use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CmsPageLoadedEvent extends NestedEvent implements ContenaChannelEvent
{
    protected CmsPageCollection $result;

    /**
     * @param CmsPageCollection $result
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'result', newType: CmsPageCollection::class)]
    public function __construct(
        protected Request $request,
        /* protected CmsPageCollection $result, */
        EntityCollection $result,
        protected ChannelContext $channelContext,
    ) {
        if (!$result instanceof CmsPageCollection) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Passing a plain EntityCollection as $result is deprecated, pass a CmsPageCollection instead.'
            );
        }

        $this->result = $result;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return CmsPageCollection
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: CmsPageCollection::class)]
    public function getResult(): EntityCollection /* CmsPageCollection */
    {
        return $this->result;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
