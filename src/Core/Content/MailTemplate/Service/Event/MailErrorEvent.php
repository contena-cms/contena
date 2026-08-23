<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use Contena\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class MailErrorEvent extends Event
{
    final public const NAME = 'mail.sent.error';

    private readonly Level $logLevel;

    /**
     * @param array<string, mixed> $templateData
     */
    public function __construct(
        private readonly Context $context,
        ?Level $logLevel = Level::Debug,
        private readonly ?\Throwable $throwable = null,
        private readonly ?string $message = null,
        private readonly ?string $template = null,
        private readonly ?array $templateData = []
    ) {
        $this->logLevel = $logLevel ?? Level::Debug;
    }

    public function getThrowable(): ?\Throwable
    {
        return $this->throwable;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLogLevel(): Level
    {
        return $this->logLevel;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogData(): array
    {
        $logData = [];

        if ($this->getThrowable()) {
            $throwable = $this->getThrowable();
            $logData['exception'] = (string) $throwable;
        }

        if ($this->message) {
            $logData['message'] = $this->message;
        }

        if ($this->template) {
            $logData['template'] = $this->template;
        }

        $logData['eventName'] = null;

        if ($this->templateData) {
            $logData['templateData'] = $this->templateData;
            $logData['eventName'] = $this->templateData['eventName'] ?? null;
        }

        return $logData;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    /**
     * @return mixed[]|null
     */
    public function getTemplateData(): ?array
    {
        return $this->templateData;
    }
}
