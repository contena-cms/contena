<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots\Parser;

use Contena\Core\Framework\Context;
use Contena\Frontend\Page\Robots\Event\RobotsDirectiveParsingEvent;
use Contena\Frontend\Page\Robots\Event\RobotsUnknownDirectiveEvent;
use Contena\Frontend\Page\Robots\Struct\RobotsDirective;
use Contena\Frontend\Page\Robots\Struct\RobotsDirectiveType;
use Contena\Frontend\Page\Robots\Struct\RobotsUserAgentBlock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RobotsDirectiveParser
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function parse(string $text, Context $context, ?string $channelId = null): ParsedRobots
    {
        $lines = explode("\n", $text);
        $userAgentBlocks = [];
        $orphanedPathDirectives = [];
        $currentUserAgents = [];
        $currentDirectives = [];
        $issues = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            ++$lineNumber;
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse directive
            $parts = explode(':', $line, 2);
            if (\count($parts) !== 2) {
                $issues[] = new ParseIssue(
                    $lineNumber,
                    $line,
                    'Malformed line: missing colon separator',
                    ParseIssueSeverity::ERROR
                );

                continue;
            }

            $directiveType = trim($parts[0]);
            $directiveValue = trim($parts[1]);

            // Validate directive
            $directiveTypeEnum = RobotsDirectiveType::tryFromInsensitive($directiveType);
            if ($directiveTypeEnum === null) {
                // Dispatch event for unknown directive
                $unknownDirectiveEvent = new RobotsUnknownDirectiveEvent(
                    $lineNumber,
                    $line,
                    $directiveType,
                    $directiveValue,
                    $currentUserAgents !== [],
                    $context,
                    $channelId
                );
                $this->eventDispatcher->dispatch($unknownDirectiveEvent);

                // If the event was handled, skip warning; otherwise add custom issue or default warning
                if (!$unknownDirectiveEvent->handled) {
                    $issue = $unknownDirectiveEvent->issue;
                    if ($issue === null) {
                        $issue = new ParseIssue(
                            $lineNumber,
                            $line,
                            'Unknown directive type: \'' . $directiveType . '\'',
                            ParseIssueSeverity::WARNING
                        );
                    }
                    $issues[] = $issue;
                }

                continue;
            }

            // Handle User-agent directive
            if ($directiveTypeEnum === RobotsDirectiveType::USER_AGENT) {
                // If we have a current block with directives, save it
                if ($currentUserAgents !== [] && $currentDirectives !== []) {
                    foreach ($currentUserAgents as $userAgent) {
                        $userAgentBlocks[] = new RobotsUserAgentBlock($userAgent, $currentDirectives);
                    }
                    // Reset for new block
                    $currentUserAgents = [];
                    $currentDirectives = [];
                }

                // Add user agent to current block
                $currentUserAgents[] = $directiveValue;

                continue;
            }

            // Handle other directives
            $directive = new RobotsDirective($directiveTypeEnum, $directiveValue);

            if ($currentUserAgents !== []) {
                // We're in a user-agent block
                $currentDirectives[] = $directive;
            } else {
                // Orphaned directive (backward compatibility)
                if ($directiveTypeEnum->isPathBased()) {
                    $orphanedPathDirectives[] = $directive;
                } else {
                    $issues[] = new ParseIssue(
                        $lineNumber,
                        $line,
                        'Directive \'' . $directiveTypeEnum->value . '\' found outside user-agent block and will be ignored',
                        ParseIssueSeverity::WARNING
                    );
                }
            }
        }

        // Save last block if any
        if ($currentUserAgents !== [] && $currentDirectives !== []) {
            foreach ($currentUserAgents as $userAgent) {
                $userAgentBlocks[] = new RobotsUserAgentBlock($userAgent, $currentDirectives);
            }
        }

        $parsedResult = new ParsedRobots($userAgentBlocks, $orphanedPathDirectives, $issues);

        // Dispatch parsing event to allow modifications
        $parsingEvent = new RobotsDirectiveParsingEvent($text, $parsedResult, $context, $channelId);
        $this->eventDispatcher->dispatch($parsingEvent);

        return $parsingEvent->parsedResult;
    }
}
