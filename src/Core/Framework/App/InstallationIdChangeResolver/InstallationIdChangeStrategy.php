<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationIdChangeResolver;

use Contena\Core\Framework\Context;

/**
 * @internal
 */
interface InstallationIdChangeStrategy
{
    public function getName(): string;

    /**
     * @return string the description of the strategy used to explain what the strategy does in CLI and API
     *
     * Note: in the administration we have separate snippets for this to localize the description, keep the descriptions in sync
     * `ct-app.component.ct-app-installation-id-change-modal.strategies.${strategy-name}.description`
     */
    public function getDescription(): string;

    public function resolve(Context $context): void;
}
