<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\ValueGenerator;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\NumberRangeEvents;
use Contena\Core\System\NumberRange\NumberRangeException;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-import-type ValueGeneratorConfig from AbstractValueGenerator
 */
class NumberRangeValueGenerator extends AbstractNumberRangeValueGenerator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ValueGeneratorPatternRegistry $valueGeneratorPatternRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection
    ) {
    }

    public function getValue(string $type, Context $context, bool $preview = false): string
    {
        $config = $this->getConfiguration($type, $context->getDataScopeId());

        $parsedPattern = $this->parsePattern($config['pattern']);

        $generatedValue = \is_array($parsedPattern) ? $this->generate($parsedPattern, $config, $context, $preview) : '';

        return $this->endEvent($generatedValue, $type, $context, $preview);
    }

    public function previewPatternByNumberRangeId(
        string $numberRangeId,
        Context $context,
        ?string $pattern = null,
        ?int $start = null,
    ): string {
        $config = $this->getConfigurationByNumberRangeId($numberRangeId, $context->getDataScopeId());

        if ($pattern) {
            $config['pattern'] = $pattern;
        }

        if ($start !== null) {
            $config['start'] = $start;
        }

        $parsedPattern = $this->parsePattern($config['pattern']);

        return \is_array($parsedPattern) ? $this->generate($parsedPattern, $config, $context, true) : '';
    }

    protected function getDecorated(): AbstractNumberRangeValueGenerator
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string>|null
     */
    private function parsePattern(?string $pattern): ?array
    {
        if (!$pattern) {
            return null;
        }

        return preg_split(
            '/([}{])/',
            $pattern,
            -1,
            \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY
        ) ?: null;
    }

    private function endEvent(string $generatedValue, string $type, Context $context, bool $preview): string
    {
        $generatedEvent = $this->eventDispatcher->dispatch(
            new NumberRangeGeneratedEvent($generatedValue, $type, $context, $preview),
            NumberRangeEvents::NUMBER_RANGE_GENERATED
        );

        return $generatedEvent->getGeneratedValue();
    }

    /**
     * Resolves a number range only inside the Context's exact data scope.
     *
     * @return array{id: string, dataScopeId: string, pattern: string, start: int, technical_name: string}
     */
    private function getConfiguration(string $definition, string $dataScopeId): array
    {
        /** @var array{id: string, dataScopeId: string, pattern: string, start: int, technical_name: string}|false $config */
        $config = $this->connection->fetchAssociative('
            SELECT LOWER(HEX(`number_range`.`id`)) AS `id`, LOWER(HEX(`number_range`.`data_scope_id`)) AS `dataScopeId`, `number_range`.`pattern`, `number_range`.`start`, `number_range_type`.`technical_name`
            FROM number_range
            INNER JOIN number_range_type ON number_range_type.id = number_range.type_id
            WHERE `number_range_type`.`technical_name` = :typeName
                AND `number_range`.`data_scope_id` = :dataScopeId
            ORDER BY number_range.global ASC
        ', [
            'typeName' => $definition,
            'dataScopeId' => Uuid::fromHexToBytes($dataScopeId),
        ]);

        if (!$config) {
            throw NumberRangeException::noConfigurationForEntity($definition);
        }

        $config['start'] = (int) $config['start'];

        return $config;
    }

    /**
     * @return array{id: string, dataScopeId: string, pattern: string, start: int}
     */
    private function getConfigurationByNumberRangeId(string $numberRangeId, string $dataScopeId): array
    {
        /** @var array{id: string, dataScopeId: string, pattern: string, start: int}|false $config */
        $config = $this->connection->fetchAssociative('
            SELECT LOWER(HEX(`number_range`.`id`)) AS `id`, LOWER(HEX(`number_range`.`data_scope_id`)) AS `dataScopeId`, `number_range`.`pattern`, `number_range`.`start`
            FROM number_range
            WHERE `number_range`.`id` = :numberRangeId
                AND `number_range`.`data_scope_id` = :dataScopeId
        ', [
            'numberRangeId' => Uuid::fromHexToBytes($numberRangeId),
            'dataScopeId' => Uuid::fromHexToBytes($dataScopeId),
        ]);

        if (!$config) {
            throw NumberRangeException::numberRangeNotFound($numberRangeId);
        }

        $config['start'] = (int) $config['start'];

        return $config;
    }

    /**
     * @param ValueGeneratorConfig $config
     * @param array<string> $parsedPattern
     */
    private function generate(array $parsedPattern, array $config, Context $context, bool $preview = false): string
    {
        $generated = '';
        $startPattern = false;

        foreach ($parsedPattern as $patternPart) {
            if ($patternPart === '}') {
                $startPattern = false;

                continue;
            }
            if ($patternPart === '{') {
                $startPattern = true;

                continue;
            }
            if ($startPattern === true) {
                $patternArg = explode('_', $patternPart);
                $pattern = array_shift($patternArg);
                $generated .= $this->valueGeneratorPatternRegistry->generatePattern(
                    $pattern,
                    $patternPart,
                    $config,
                    $context,
                    $patternArg,
                    $preview,
                );

                $startPattern = false;

                continue;
            }
            $generated .= $patternPart;
        }

        return $generated;
    }
}
