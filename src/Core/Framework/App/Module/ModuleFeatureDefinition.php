<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Module;

use Contena\Core\Framework\App\Feature\AppFeatureConfig;
use Contena\Core\Framework\App\Feature\AppFeatureDefinition;
use Contena\Core\Framework\App\Feature\TranslatedString;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Administration\Module as ManifestModule;
use Contena\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @extends AppFeatureDefinition<ModuleConfig>
 *
 * @phpstan-type ModulePayload array{modules: list<array{name: string, label: array<string, string>, parent: string|null, source: string|null, position: int}>, mainModule: array{source: string}|null}
 */
class ModuleFeatureDefinition extends AppFeatureDefinition
{
    public function getType(): string
    {
        return 'module';
    }

    public function getConfigClass(): string
    {
        return ModuleConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        $admin = $manifest->getAdmin();
        if ($admin === null) {
            return [];
        }

        $modules = array_map(
            static fn (ManifestModule $module): Module => new Module(
                $module->getName(),
                new TranslatedString($module->getLabel()),
                $module->getParent(),
                $module->getSource(),
                $module->getPosition(),
            ),
            $admin->getModules(),
        );

        $mainModule = $admin->getMainModule() !== null
            ? new MainModule($admin->getMainModule()->getSource())
            : null;

        if ($modules === [] && $mainModule === null) {
            return [];
        }

        return [new ModuleConfig($modules, $mainModule)];
    }

    /**
     * @return ModulePayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return [
            'modules' => array_map(
                static fn (Module $module): array => [
                    'name' => $module->name,
                    'label' => $module->label->all(),
                    'parent' => $module->parent,
                    'source' => $module->source,
                    'position' => $module->position,
                ],
                $declared->modules,
            ),
            'mainModule' => $declared->mainModule !== null ? ['source' => $declared->mainModule->source] : null,
        ];
    }

    /**
     * @param ModulePayload $payload
     */
    public function fromPayload(array $payload): ModuleConfig
    {
        $modules = array_map(
            static fn (array $module): Module => new Module(
                $module['name'],
                new TranslatedString($module['label']),
                $module['parent'],
                $module['source'],
                $module['position'],
            ),
            $payload['modules'],
        );

        $mainModule = $payload['mainModule'] !== null ? new MainModule($payload['mainModule']['source']) : null;

        return new ModuleConfig($modules, $mainModule);
    }
}
