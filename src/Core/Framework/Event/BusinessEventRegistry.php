<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\Media\Event\MediaUploadedEvent;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;

class BusinessEventRegistry
{
    /**
     * @var list<class-string<FlowEventAware>>
     */
    private array $classes = [
        MailBeforeSentEvent::class,
        MailBeforeValidateEvent::class,
        MailSentEvent::class,
        MediaUploadedEvent::class,
        UserRecoveryRequestEvent::class,
    ];

    /**
     * @param list<class-string<FlowEventAware>> $classes
     */
    public function addClasses(array $classes): void
    {
        $this->classes = array_values(array_unique([...$this->classes, ...$classes]));
    }

    /**
     * @return list<class-string<FlowEventAware>>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }
}
