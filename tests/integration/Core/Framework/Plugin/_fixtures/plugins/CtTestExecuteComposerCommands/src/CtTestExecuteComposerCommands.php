<?php declare(strict_types=1);

namespace CtTestExecuteComposerCommands;

use Contena\Core\Framework\Plugin;

class CtTestExecuteComposerCommands extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }
}
