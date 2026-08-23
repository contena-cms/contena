<?php declare(strict_types=1);

namespace CtTestPlugin;

use Contena\Core\Content\Tag\TagCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Parameter\AdditionalBundleParameters;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\Context\ActivateContext;
use Contena\Core\Framework\Plugin\Context\DeactivateContext;
use Contena\Core\Framework\Plugin\Context\UninstallContext;
use Contena\Core\Framework\Plugin\Context\UpdateContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Tests\Integration\Core\Framework\Plugin\_fixtures\bundles\FooBarBundle;
use Contena\Tests\Integration\Core\Framework\Plugin\_fixtures\bundles\GizmoBundle;
use Symfony\Contracts\Service\Attribute\Required;

class CtTestPlugin extends Plugin
{
    final public const string PLUGIN_LABEL = 'English plugin name';

    final public const string PLUGIN_VERSION = '1.0.1';

    final public const string PLUGIN_OLD_VERSION = '1.0.0';

    final public const string PLUGIN_GERMAN_LABEL = 'Deutscher Pluginname';

    final public const string THROW_ERROR_ON_UPDATE = 'throw-error-on-update';
    final public const string THROW_ERROR_ON_DEACTIVATE = 'throw-error-on-deactivate';

    public ?SystemConfigService $systemConfig = null;

    /**
     * @var EntityRepository<TagCollection>|null
     */
    public ?EntityRepository $tagRepository = null;

    public ?ActivateContext $preActivateContext = null;

    public ?ActivateContext $postActivateContext = null;

    public ?DeactivateContext $preDeactivateContext = null;

    public ?DeactivateContext $postDeactivateContext = null;

    #[Required]
    public function requiredSetterOfPrivateService(SystemConfigService $systemConfig): void
    {
        $this->systemConfig = $systemConfig;
    }

    /**
     * @param EntityRepository<TagCollection> $tagRepository
     */
    public function manualSetter(EntityRepository $tagRepository): void
    {
        $this->tagRepository = $tagRepository;
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
    }

    public function update(UpdateContext $updateContext): void
    {
        if ($updateContext->getContext()->hasExtension(self::THROW_ERROR_ON_UPDATE)) {
            throw new \BadMethodCallException('Update throws an error');
        }

        parent::update($updateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        if ($deactivateContext->getContext()->hasExtension(self::THROW_ERROR_ON_DEACTIVATE)) {
            throw new \BadFunctionCallException('Deactivate throws an error');
        }
        parent::deactivate($deactivateContext);
    }

    public function getMigrationNamespace(): string
    {
        return $_SERVER['FAKE_MIGRATION_NAMESPACE'] ?? parent::getMigrationNamespace();
    }

    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        require_once __DIR__ . '/../../../bundles/FooBarBundle.php';
        require_once __DIR__ . '/../../../bundles/GizmoBundle.php';

        return [
            new FooBarBundle(),
            -10 => new GizmoBundle(),
        ];
    }
}
