<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Handler;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\App\Manifest\Xml\Administration\Module;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ModuleLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(private readonly EntityRepository $appRepository)
    {
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
        if (!$context->app->getAppSecret()) {
            return;
        }

        $payload = [
            'id' => $context->app->getId(),
            'mainModule' => null,
            'modules' => [],
        ];

        $admin = $context->manifest->getAdmin();
        if ($admin !== null) {
            if ($admin->getMainModule() !== null) {
                $payload['mainModule'] = [
                    'source' => $admin->getMainModule()->getSource(),
                ];
            }

            $payload['modules'] = array_map(
                fn (Module $module) => $module->toArray($context->defaultLocale),
                $admin->getModules()
            );
        }

        $this->appRepository->update([$payload], $context->context);
    }
}
