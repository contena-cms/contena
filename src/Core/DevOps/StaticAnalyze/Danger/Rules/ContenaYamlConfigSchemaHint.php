<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;

/**
 * The `config-schema.json` describes the `contena.yaml` structure and should follow its changes.
 *
 * @internal
 */
class ContenaYamlConfigSchemaHint
{
    public function __invoke(Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        $contenaYamlTouched = $files->matches('*/contena.yaml')->count() > 0;
        $configSchemaTouched = $files->matches('config-schema.json')->count() > 0;

        if ($contenaYamlTouched && !$configSchemaTouched) {
            $context->warning('You updated the contena.yaml, please consider to update the config-schema.json');
        }
    }
}
