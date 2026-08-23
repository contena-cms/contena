<?php declare(strict_types=1);

use Danger\Config;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\AgenticCommercePluginHint;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\ComposerVersionConstraints;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\DangerConfigChanged;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\DeprecatedChangelogFormat;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\EntityRepositoryInFrontendLayer;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\IgnoredPhpstanErrorsInTouchedFiles;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\InlineRuleInDangerConfig;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\InvalidFileNameCharacters;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\LegacyTestsInSrc;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\MissingIntegrationTestInSplitSuite;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\MissingMigrationTests;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\MissingReleaseInfo;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\MissingUnitTests;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\PhpstanBaselineGrowth;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\RemovedTwigBlocks;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\RouteSnapshotExtension;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\ContenaYamlConfigSchemaHint;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\SqlHeredocUsage;

// danger runs on its own vendor-bin autoloader (vendor-bin/danger-php), which does not know the
// Contena namespaces — load the rule classes directly instead
foreach (glob(__DIR__ . '/src/Core/DevOps/StaticAnalyze/Danger/Rules/*.php') ?: [] as $ruleFile) {
    require_once $ruleFile;
}

return (new Config())
    ->useThreadOn(Config::REPORT_LEVEL_WARNING)
    ->useRule(new DangerConfigChanged())
    ->useRule(new InlineRuleInDangerConfig())
    ->useRule(new DeprecatedChangelogFormat())
    ->useRule(new MissingReleaseInfo())
    ->useRule(new IgnoredPhpstanErrorsInTouchedFiles())
    ->useRule(new PhpstanBaselineGrowth())
    ->useRule(new EntityRepositoryInFrontendLayer())
    ->useRule(new ContenaYamlConfigSchemaHint())
    ->useRule(new AgenticCommercePluginHint())
    ->useRule(new MissingMigrationTests())
    ->useRule(new SqlHeredocUsage())
    ->useRule(new RemovedTwigBlocks())
    ->useRule(new InvalidFileNameCharacters())
    ->useRule(new LegacyTestsInSrc())
    ->useRule(new MissingUnitTests())
    ->useRule(new ComposerVersionConstraints())
    ->useRule(new MissingIntegrationTestInSplitSuite())
    ->useRule(new RouteSnapshotExtension())
;
