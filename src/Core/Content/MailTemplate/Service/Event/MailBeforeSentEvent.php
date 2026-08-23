<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use Contena\Core\Content\Flow\Dispatching\Aware\MessageAware;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\ArrayType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\ObjectType;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\Event;

class MailBeforeSentEvent extends Event implements MessageAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'mail.after.create.message';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly Email $message,
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
            ->add('data', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)))
            ->add(MessageAware::MESSAGE, new ObjectType());
    }

    public function getValues(): array
    {
        return ['data' => $this->data];
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getMessage(): Email
    {
        return $this->message;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogData(): array
    {
        $data = $this->data;
        unset($data['binAttachments']);

        return [
            'data' => $data,
            'eventName' => $this->eventName,
            'message' => $this->message,
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Info;
    }
}
