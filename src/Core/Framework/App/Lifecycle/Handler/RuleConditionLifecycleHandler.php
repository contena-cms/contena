<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Handler;

use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppHandlerIdentifier;
use Contena\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\App\Lifecycle\ScriptFileReader;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Contena\Core\Framework\Validation\Constraint\Uuid;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\BoolField;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\CustomFieldType;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\FloatField;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\IntField;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\MediaSelectionField;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\MultiEntitySelectField;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\MultiSelectField;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\SingleEntitySelectField;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\SingleSelectField;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal only for use by the app-system
 */
class RuleConditionLifecycleHandler extends AbstractLifecycleHandler
{
    private const CONDITION_SCRIPT_DIR = '/rule-conditions/';

    /**
     * @param EntityRepository<AppScriptConditionCollection> $appScriptConditionRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly ScriptFileReader $scriptReader,
        private readonly EntityRepository $appScriptConditionRepository,
        private readonly EntityRepository $appRepository
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

    public function activate(AppActivationContext $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $context->app->getId()));
        $criteria->addFilter(new EqualsFilter('active', false));

        $scripts = $this->appScriptConditionRepository->searchIds($criteria, $context->context)->getIds();

        $updateSet = array_map(static fn (string $id) => ['id' => $id, 'active' => true], $scripts);

        $this->appScriptConditionRepository->update($updateSet, $context->context);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $context->app->getId()));
        $criteria->addFilter(new EqualsFilter('active', true));

        $scripts = $this->appScriptConditionRepository->searchIds($criteria, $context->context)->getIds();

        $updateSet = array_map(static fn (string $id) => ['id' => $id, 'active' => false], $scripts);

        $this->appScriptConditionRepository->update($updateSet, $context->context);
    }

    private function persist(AppPersistContext $context): void
    {
        $app = $this->getAppWithExistingConditions($context->app->getId(), $context->context);
        $existingRuleConditions = $app->getScriptConditions();
        \assert($existingRuleConditions !== null);

        $ruleConditions = $context->manifest->getRuleConditions();
        $ruleConditions = $ruleConditions !== null ? $ruleConditions->getRuleConditions() : [];

        $upserts = [];

        foreach ($ruleConditions as $ruleCondition) {
            $payload = $ruleCondition->toArray($context->defaultLocale);
            $payload['identifier'] = AppHandlerIdentifier::build($context->manifest->getMetadata()->getName(), $ruleCondition->getIdentifier());
            $payload['script'] = $this->scriptReader->getScriptContent(
                $app,
                self::CONDITION_SCRIPT_DIR . $ruleCondition->getScript(),
            );
            $payload['appId'] = $context->app->getId();
            $payload['active'] = $app->isActive();
            $payload['constraints'] = $this->hydrateConstraints($payload['constraints']);

            $existing = $existingRuleConditions->filterByProperty('identifier', $payload['identifier'])->first();

            if ($existing) {
                $existingRuleConditions->remove($existing->getId());
                $payload['id'] = $existing->getId();
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $context->context->scope(Context::SYSTEM_SCOPE, function (Context $innerContext) use ($upserts): void {
                $this->appScriptConditionRepository->upsert($upserts, $innerContext);
            });
        }

        $this->deleteConditionScripts($existingRuleConditions, $context->context);
    }

    private function getAppWithExistingConditions(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('scriptConditions');

        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();
        \assert($app !== null);

        return $app;
    }

    private function deleteConditionScripts(AppScriptConditionCollection $toBeRemoved, Context $context): void
    {
        $ids = $toBeRemoved->getIds();

        if ($ids !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->appScriptConditionRepository->delete($ids, $context);
        }
    }

    /**
     * @param list<CustomFieldType> $fields
     */
    private function hydrateConstraints(array $fields): string
    {
        $constraints = [];

        foreach ($fields as $field) {
            $constraints[$field->getName()] = [];

            if ($field->getRequired()) {
                $constraints[$field->getName()][] = new NotBlank();
            }

            if ($field instanceof BoolField) {
                $constraints[$field->getName()][] = new Type('bool');

                continue;
            }

            if ($field instanceof FloatField) {
                $constraints[$field->getName()][] = new Type('numeric');

                continue;
            }

            if ($field instanceof IntField) {
                $constraints[$field->getName()][] = new Type('int');

                continue;
            }

            if ($field instanceof MultiEntitySelectField) {
                $constraints[$field->getName()][] = new ArrayOfUuid();

                continue;
            }

            if ($field instanceof SingleEntitySelectField || $field instanceof MediaSelectionField) {
                $constraints[$field->getName()][] = new Uuid();

                continue;
            }

            if ($field instanceof MultiSelectField) {
                $constraints[$field->getName()][] = new All(constraints: new Choice(choices: array_keys($field->getOptions())));

                continue;
            }

            if ($field instanceof SingleSelectField) {
                $constraints[$field->getName()][] = new Choice(choices: array_keys($field->getOptions()));

                continue;
            }

            $constraints[$field->getName()][] = new Type('string');
        }

        return serialize($constraints);
    }
}
