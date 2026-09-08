<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Handler;

use Contena\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Contena\Core\Framework\App\Lifecycle\Persister\ContentSystemElementTypePersister;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;

/**
 * @internal only for use by the app-system
 */
class ContentSystemElementTypeLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly ContentSystemElementTypePersister $persister,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persister->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persister->persist($context);
    }

    public function activate(AppActivationContext $context): void
    {
        $this->registry->invalidate();
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->registry->invalidate();
    }

    public function uninstall(AppRemovalContext $context): void
    {
        $this->registry->invalidate();
    }

    public function delete(AppRemovalContext $context): void
    {
        $this->registry->invalidate();
    }
}
