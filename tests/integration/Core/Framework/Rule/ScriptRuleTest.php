<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Contena\Core\Framework\Adapter\Twig\TwigEnvironment;
use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Contena\Core\Framework\App\Lifecycle\AppLifecycle;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Rule\Container\AndRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\ScriptRule;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\Framework\Script\Execution\ScriptEnvironmentFactory;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Twig\Cache\FilesystemCache;
use Twig\Extension\DebugExtension;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class ScriptRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private AbstractAppLifecycle $appLifecycle;

    private Context $context;

    private string $scriptId;

    private string $appId;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->appLifecycle = static::getContainer()->get(AppLifecycle::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * @param array<string, string> $values
     */
    #[DataProvider('scriptProvider')]
    public function testRuleScriptExecution(string $path, array $values, bool $expectedTrue): void
    {
        $script = file_get_contents(__DIR__ . $path);
        $scope = new CheckoutRuleScope($this->createChannelContext());
        $rule = new ScriptRule();

        $rule->assign([
            // add a random id, to prevent twig opcache from interfering with the test
            'identifier' => Uuid::randomHex(),
            'values' => $values,
            'script' => $script,
        ]);

        $rule->configureDependencies(static::getContainer());

        if ($expectedTrue) {
            static::assertTrue($rule->match($scope));
        } else {
            static::assertFalse($rule->match($scope));
        }
    }

    public static function scriptProvider(): \Generator
    {
        yield 'simple script return true' => ['/_fixture/scripts/simple.twig', ['test' => 'foo'], true];
        yield 'simple script return false' => ['/_fixture/scripts/simple.twig', ['test' => 'bar'], false];
    }

    public function testRuleScriptIsCached(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $scope = new CheckoutRuleScope($channelContext);
        $rule = new ScriptRule();
        $container = new Container(new ParameterBag([
            'twig.cache' => sys_get_temp_dir(),
            'kernel.debug' => false, // we need to set debug to false to enable caching
        ]));
        $twigFactory = new DebuggableScriptEnvironmentFactory();
        $container->set(ScriptEnvironmentFactory::class, $twigFactory);
        $container->set(ScriptTraces::class, new ScriptTraces(new NativeClock()));

        $rule->configureDependencies($container);
        $rule->assign([
            'script' => '{% return true %}',
            'values' => [],
        ]);

        static::assertTrue($rule->match($scope));

        $twig = $twigFactory->environment;
        static::assertInstanceOf(TwigEnvironment::class, $twig);
        $cache = $twig->getCache();
        static::assertInstanceOf(FilesystemCache::class, $cache);
        static::assertTrue($twig->getLoader()->exists('scriptRule'));
        static::assertGreaterThan(
            0,
            $cache->getTimestamp(
                $cache->generateKey('scriptRule', $twig->getTemplateClass('scriptRule'))
            )
        );
    }

    public function testRuleIsConsistent(): void
    {
        $this->installApp();
        $ruleId = Uuid::randomHex();
        $conditionId = Uuid::randomHex();
        $expectedTrueScope = $this->getCheckoutScope($ruleId, $conditionId);

        $channelContext = static::createStub(ChannelContext::class);
        $user = new UserEntity();
        $user->setGroupId(Uuid::randomHex());
        $channelContext->method('getUser')->willReturn($user);
        $expectedFalseScope = new CheckoutRuleScope($channelContext);

        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->getEntities()->get($ruleId);
        static::assertInstanceOf(RuleEntity::class, $rule);
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);
        static::assertFalse($payload->match($expectedFalseScope));
        static::assertTrue($payload->match($expectedTrueScope));

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $conditionId]], $this->context);
    }

    public function testRuleValidationFails(): void
    {
        $this->installApp();

        try {
            $ruleId = Uuid::randomHex();
            $this->ruleRepository->create(
                [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
                Context::createDefaultContext()
            );

            $id = Uuid::randomHex();
            $this->conditionRepository->create([
                [
                    'id' => $id,
                    'type' => (new ScriptRule())->getName(),
                    'ruleId' => $ruleId,
                    'scriptId' => $this->scriptId,
                    'value' => [
                        'operator' => 'foo',
                    ],
                ],
            ], $this->context);

            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors(), false);
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $exceptions[0]['code']);
            static::assertSame('/0/value/userGroupIds', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public static function manifestPathProvider(): \Generator
    {
        yield 'Default fixture App with userGroupIds property' => [
            '/test/manifest.xml',
            [
                'operator' => '=',
                'userGroupIds' => [Uuid::randomHex()],
            ],
        ];

        yield 'App with firstName as rule property' => [
            '/test/manifest_arbitraryRule_firstName.xml',
            [
                'operator' => '=',
                'name' => 'hello',
            ],
        ];

        yield 'App with existing constraints name as rule property' => [
            '/test/manifest_arbitraryRule_constraints.xml',
            [
                'operator' => '=',
                'constraints' => 'broken',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $value
     */
    #[DataProvider('manifestPathProvider')]
    public function testRuleValidationSucceedsWithArbitraryProperties(string $manifestPath, array $value): void
    {
        $fixturesPath = __DIR__ . '/../App/Manifest/_fixtures';
        $manifest = Manifest::createFromXmlFile($fixturesPath . $manifestPath);
        $this->setupApp($manifest);

        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new ScriptRule())->getName(),
                'ruleId' => $ruleId,
                'scriptId' => $this->scriptId,
                'value' => $value,
            ],
        ], $this->context);

        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->getEntities()->get($ruleId);
        static::assertInstanceOf(RuleEntity::class, $rule);
        $payload = $rule->getPayload();
        static::assertInstanceOf(AndRule::class, $payload);

        $scriptRule = $payload->getRules()[0];
        static::assertInstanceOf(ScriptRule::class, $scriptRule);
        static::assertSame($value, $scriptRule->getValues());
        static::assertSame([], $scriptRule->getConstraints());

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }

    public function testRuleWithInactiveScript(): void
    {
        $this->installApp();
        $ruleId = Uuid::randomHex();
        $conditionId = Uuid::randomHex();
        $scope = $this->getCheckoutScope($ruleId, $conditionId);

        $this->appLifecycle->deactivate($this->appId, $this->context);

        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->getEntities()->get($ruleId);
        static::assertInstanceOf(RuleEntity::class, $rule);
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);
        static::assertFalse($payload->match($scope));

        $this->appLifecycle->activate($this->appId, $this->context);

        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->getEntities()->get($ruleId);
        static::assertInstanceOf(RuleEntity::class, $rule);
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);
        static::assertTrue($payload->match($scope));

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $conditionId]], $this->context);
    }

    public function testRuleWithUninstalledApp(): void
    {
        $this->installApp();
        $ruleId = Uuid::randomHex();
        $conditionId = Uuid::randomHex();
        $scope = $this->getCheckoutScope($ruleId, $conditionId);

        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->getEntities()->get($ruleId);
        static::assertInstanceOf(RuleEntity::class, $rule);
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);
        static::assertTrue($payload->match($scope));

        $this->appLifecycle->uninstall('test', ['id' => $this->appId], $this->context);

        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->getEntities()->get($ruleId);
        static::assertInstanceOf(RuleEntity::class, $rule);
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);
        static::assertFalse($payload->match($scope));

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $conditionId]], $this->context);
    }

    public function testRuleValueAssignment(): void
    {
        $rule = new ScriptRule();
        $value = [
            'operator' => '=',
            'userGroupIds' => [Uuid::randomHex()],
        ];
        $rule->assignValues($value);

        static::assertSame($value, $rule->getValues());
    }

    private function getCheckoutScope(string $ruleId, string $conditionId): CheckoutRuleScope
    {
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        $groupId = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $conditionId,
                'type' => (new ScriptRule())->getName(),
                'ruleId' => $ruleId,
                'scriptId' => $this->scriptId,
                'value' => [
                    'userGroupIds' => [Uuid::randomHex(), $groupId],
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        $channelContext = static::createStub(ChannelContext::class);
        $user = new UserEntity();

        $user->setGroupId($groupId);
        $channelContext->method('getUser')->willReturn($user);

        return new CheckoutRuleScope($channelContext);
    }

    private function installApp(): void
    {
        $fixturesPath = __DIR__ . '/../App/Manifest/_fixtures';

        $manifest = Manifest::createFromXmlFile($fixturesPath . '/test/manifest.xml');
        $this->setupApp($manifest);
    }

    private function setupApp(Manifest $manifest): void
    {
        $this->appLifecycle->install($manifest, new AppInstallParameters(activate: false), $this->context);

        $app = $this->appRepository->search((new Criteria())->addAssociation('scriptConditions'), $this->context)->getEntities()->first();
        static::assertInstanceOf(AppEntity::class, $app);
        $this->appId = $app->getId();
        $this->appLifecycle->activate($this->appId, $this->context);
        $conditions = $app->getScriptConditions();
        static::assertInstanceOf(AppScriptConditionCollection::class, $conditions);
        $condition = $conditions->first();
        static::assertInstanceOf(AppScriptConditionEntity::class, $condition);
        $this->scriptId = $condition->getId();
    }

    private function createChannelContext(): ChannelContext
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        return $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }
}

/**
 * @internal
 */
class DebuggableScriptEnvironmentFactory extends ScriptEnvironmentFactory
{
    public ?TwigEnvironment $environment = null;

    public function __construct()
    {
        parent::__construct(new DebugExtension(), [
            new PhpSyntaxExtension(),
        ], '6.7.0.0');
    }

    public function initEnv(Script $script): TwigEnvironment
    {
        $this->environment = parent::initEnv($script);

        return $this->environment;
    }
}
