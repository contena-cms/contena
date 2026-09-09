<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Handler;

use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\System\CustomField\CustomFieldSetPersister;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFields;

/**
 * @internal only for use by the app-system
 */
class CustomFieldLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly CustomFieldSetPersister $customFieldSetPersister,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    private function persist(AppPersistContext $context): void
    {
        if ($context->appFilesystem->hasFile('Resources', 'config', 'custom-fields.xml')) {
            $customFields = CustomFieldXmlLoader::load(
                $context->appFilesystem->path('Resources', 'config', 'custom-fields.xml')
            );
        } else {
            $customFields = CustomFields::fromArray([]);
        }

        $this->customFieldSetPersister->sync(
            $customFields,
            $context->app->getName(),
            $context->context
        );
    }
}
