<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Events;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\DataBag;

#[Package('after-sales')]
class FlowSendMailActionEvent implements ContenaEvent
{
    public function __construct(
        private readonly DataBag $dataBag,
        private readonly MailTemplateEntity $mailTemplate,
        private readonly StorableFlow $flow
    ) {
    }

    public function getContext(): Context
    {
        return $this->flow->getContext();
    }

    public function getDataBag(): DataBag
    {
        return $this->dataBag;
    }

    public function getMailTemplate(): MailTemplateEntity
    {
        return $this->mailTemplate;
    }

    public function getStorableFlow(): StorableFlow
    {
        return $this->flow;
    }
}
