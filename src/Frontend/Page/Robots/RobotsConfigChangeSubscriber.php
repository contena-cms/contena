<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Contena\Frontend\Page\Robots\Parser\ParseIssueSeverity;
use Contena\Frontend\Page\Robots\Parser\RobotsDirectiveParser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class RobotsConfigChangeSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RobotsDirectiveParser $parser,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== 'core.basicInformation.robotsRules') {
            return;
        }

        $value = $event->getValue();
        if (!\is_string($value) || $value === '') {
            return;
        }

        $channelId = $event->getChannelId();
        $parsed = $this->parser->parse($value, Context::createDefaultContext(), $channelId);

        if ($parsed->issues === []) {
            return;
        }

        $scope = $channelId === null ? 'Global' : $channelId;

        foreach ($parsed->issues as $issue) {
            $message = \sprintf(
                'Robots.txt parsing issue at line %d: %s',
                $issue->lineNumber,
                $issue->reason
            );

            $context = [
                'scope' => $scope,
                'lineNumber' => $issue->lineNumber,
                'lineContent' => $issue->lineContent,
                'severity' => $issue->severity->value,
            ];

            if ($issue->severity === ParseIssueSeverity::ERROR) {
                $this->logger->error($message, $context);
            } else {
                $this->logger->warning($message, $context);
            }
        }
    }
}
