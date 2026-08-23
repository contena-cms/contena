<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Content\Mail\Service\AbstractMailFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\ArrayType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @phpstan-import-type MailNameCombination from AbstractMailFactory
 * @phpstan-import-type Contents from AbstractMailFactory
 */
class MailSentEvent extends Event implements ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'mail.sent';

    /**
     * @param MailNameCombination $recipients
     * @param Contents $contents
     */
    public function __construct(
        private readonly string $subject,
        private readonly array $recipients,
        private readonly array $contents,
        private readonly Context $context,
        private readonly ?string $eventName = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add('subject', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('contents', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)))
            ->add('recipients', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)));
    }

    public function getValues(): array
    {
        return [
            'subject' => $this->subject,
            'contents' => $this->contents,
            'recipients' => $this->recipients,
        ];
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return Contents
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    /**
     * @return MailNameCombination
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogData(): array
    {
        return [
            'eventName' => $this->eventName,
            'subject' => $this->subject,
            'recipients' => $this->recipients,
            'contents' => $this->contents,
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Info;
    }
}
