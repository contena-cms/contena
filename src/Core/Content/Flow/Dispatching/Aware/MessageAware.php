<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Aware;

use Contena\Core\Framework\Event\IsFlowEventAware;
use Symfony\Component\Mime\Email;

#[IsFlowEventAware]
interface MessageAware
{
    public const MESSAGE = 'message';

    public function getMessage(): Email;
}
