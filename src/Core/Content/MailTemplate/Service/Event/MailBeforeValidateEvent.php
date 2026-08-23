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
 * @phpstan-import-type MailData from AbstractMailFactory
 */
class MailBeforeValidateEvent extends Event implements ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'mail.before.send';

    /**
     * @param MailData $data
     * @param array<string, mixed> $templateData
     */
    public function __construct(
        private array $data,
        private readonly Context $context,
        private array $templateData = []
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
            ->add('templateData', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)));
    }

    /**
     * @return array{data: MailData, templateData: array<string, mixed>}
     */
    public function getValues(): array
    {
        return [
            'data' => $this->data,
            'templateData' => $this->templateData,
        ];
    }

    /**
     * @return MailData
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param MailData $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @param float|int|string|array<mixed>|object $value
     */
    public function addData(string $key, $value): void
    {
        /** @phpstan-ignore assign.propertyType (To fix this issue, each allowed array key would need to be checked for its allowed value) */
        $this->data[$key] = $value;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }

    /**
     * @param float|int|string|array<mixed>|object $value
     */
    public function addTemplateData(string $key, $value): void
    {
        $this->templateData[$key] = $value;
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
            'eventName' => $this->templateData['eventName'] ?? null,
            'templateData' => $this->templateData,
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Info;
    }
}
