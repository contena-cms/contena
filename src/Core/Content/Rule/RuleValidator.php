<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Rule\Exception\InvalidConditionException;
use Contena\Core\Framework\Rule\ScriptRule;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
class RuleValidator implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<RuleConditionCollection> $ruleConditionRepository
     * @param EntityRepository<AppScriptConditionCollection> $appScriptConditionRepository
     */
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly RuleConditionRegistry $ruleConditionRegistry,
        private readonly EntityRepository $ruleConditionRepository,
        private readonly EntityRepository $appScriptConditionRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $writeException = $event->getExceptions();
        $updateQueue = [];

        foreach ($event->getCommandsForEntity(RuleConditionDefinition::ENTITY_NAME) as $command) {
            if ($command instanceof DeleteCommand) {
                continue;
            }

            if ($command instanceof InsertCommand) {
                $this->validateCondition(null, $command, $writeException, $event->getContext());
                continue;
            }

            if ($command instanceof UpdateCommand) {
                $updateQueue[] = $command;
                continue;
            }

            throw RuleException::unsupportedCommandType($command);
        }

        if ($updateQueue !== []) {
            $this->validateUpdateCommands($updateQueue, $writeException, $event);
        }
    }

    private function validateCondition(
        ?RuleConditionEntity $condition,
        WriteCommand $command,
        WriteException $writeException,
        Context $context,
    ): void {
        $payload = $command->getPayload();
        $violations = new ConstraintViolationList();
        $type = $this->getConditionType($condition, $payload);
        if ($type === null) {
            return;
        }

        try {
            $rule = $this->ruleConditionRegistry->getRuleInstance($type);
        } catch (InvalidConditionException) {
            $violations->add($this->buildViolation(
                'This {{ value }} is not a valid condition type.',
                ['{{ value }}' => $type],
                '/type',
                'CONTENT__INVALID_RULE_TYPE_EXCEPTION'
            ));
            $writeException->add(new WriteConstraintViolationException($violations, $command->getPath()));

            return;
        }

        $value = $this->getConditionValue($condition, $payload);
        $missingProperties = $rule instanceof ScriptRule ? [] : array_filter(
            $value,
            static fn (string $key): bool => !property_exists($rule, $key) && !\array_key_exists($key, $rule->getConstraints()),
            \ARRAY_FILTER_USE_KEY
        );

        foreach (array_keys($missingProperties) as $missingProperty) {
            $violations->add($this->buildViolation(
                'The property "{{ fieldName }}" is not allowed.',
                ['{{ fieldName }}' => $missingProperty],
                '/value/' . $missingProperty
            ));
        }

        $value = array_diff_key($value, $missingProperties);
        if ($rule instanceof ScriptRule) {
            $rule->assignValues($value);
            $this->setScriptConstraints($rule, $condition, $payload, $context);
        } else {
            $rule->assign($value);
        }
        $this->validateConsistency($rule->getConstraints(), $value, $violations, $missingProperties);

        if ($violations->count() > 0) {
            $writeException->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }

    /**
     * @param array<mixed> $payload
     */
    private function getConditionType(?RuleConditionEntity $condition, array $payload): ?string
    {
        $type = $payload['type'] ?? $condition?->getType();

        return \is_string($type) ? $type : null;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function getConditionValue(?RuleConditionEntity $condition, array $payload): array
    {
        $value = $condition?->getValue() ?? [];
        if (\array_key_exists('value', $payload)) {
            $value = $payload['value'] !== null
                ? json_decode((string) $payload['value'], true, 512, \JSON_THROW_ON_ERROR)
                : [];
        }

        return \is_array($value) ? $value : [];
    }

    /**
     * @param array<string, list<Constraint>> $fieldValidations
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $missingProperties
     */
    private function validateConsistency(
        array $fieldValidations,
        array $payload,
        ConstraintViolationList $violations,
        array $missingProperties,
    ): void {
        foreach ($fieldValidations as $fieldName => $validations) {
            $violations->addAll(
                $this->validator->startContext()
                    ->atPath('/value/' . $fieldName)
                    ->validate($payload[$fieldName] ?? null, $validations)
                    ->getViolations()
            );
        }

        foreach ($payload as $fieldName => $_value) {
            if (!\array_key_exists($fieldName, $fieldValidations) && $fieldName !== '_name' && !isset($missingProperties[$fieldName])) {
                $violations->add($this->buildViolation(
                    'The property "{{ fieldName }}" is not allowed.',
                    ['{{ fieldName }}' => $fieldName],
                    '/value/' . $fieldName
                ));
            }
        }
    }

    /**
     * @param list<UpdateCommand> $commands
     */
    private function validateUpdateCommands(array $commands, WriteException $writeException, PreWriteValidationEvent $event): void
    {
        $ids = array_map(
            static fn (UpdateCommand $command): string => Uuid::fromBytesToHex($command->getPrimaryKey()['id']),
            $commands
        );
        $criteria = new Criteria($ids);
        $criteria->addAssociation('appScriptCondition');
        $criteria->setLimit(null);
        $conditions = $this->ruleConditionRepository->search($criteria, $event->getContext())->getEntities();

        foreach ($commands as $command) {
            $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
            $this->validateCondition($conditions->get($id), $command, $writeException, $event->getContext());
        }
    }

    /**
     * @param array<mixed> $payload
     */
    private function setScriptConstraints(ScriptRule $rule, ?RuleConditionEntity $condition, array $payload, Context $context): void
    {
        $script = null;
        if (isset($payload['script_id']) && \is_string($payload['script_id'])) {
            $scriptId = Uuid::fromBytesToHex($payload['script_id']);
            $script = $this->appScriptConditionRepository->search(new Criteria([$scriptId]), $context)->getEntities()->first();
        } elseif ($condition?->getAppScriptCondition() instanceof AppScriptConditionEntity) {
            $script = $condition->getAppScriptCondition();
        }

        if ($script instanceof AppScriptConditionEntity && \is_array($script->getConstraints())) {
            $rule->setConstraints($script->getConstraints());
        }
    }

    /**
     * @param array<string, string> $parameters
     */
    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        ?string $propertyPath = null,
        ?string $code = null,
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            null,
            null,
            $code
        );
    }
}
