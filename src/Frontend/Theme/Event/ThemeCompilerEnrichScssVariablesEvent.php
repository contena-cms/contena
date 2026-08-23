<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ThemeCompilerEnrichScssVariablesEvent extends Event implements ContenaEvent
{
    /**
     * @param array<string, string|int|null> $variables
     */
    public function __construct(
        private array $variables,
        private readonly string $channelId,
        private readonly Context $context
    ) {
    }

    public function addVariable(string $name, string $value, bool $sanitize = false): void
    {
        if ($sanitize) {
            $this->variables[$name] = '\'' . addslashes($value) . '\'';
        } else {
            $this->variables[$name] = $value;
        }
    }

    /**
     * @return array<string, string|int|null>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
