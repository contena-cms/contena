<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule;

use Contena\Core\Framework\Adapter\Twig\TwigEnvironment;
use Contena\Core\Framework\App\Event\Hooks\AppScriptConditionHook;
use Contena\Core\Framework\Script\Debugging\Debug;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\Framework\Script\Execution\ScriptEnvironmentFactory;
use Contena\Core\Framework\Script\ScriptException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Twig\Cache\FilesystemCache;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @final
 */
class ScriptRule extends Rule
{
    final public const string RULE_NAME = 'scriptRule';

    protected string $script = '';

    /**
     * @var array<string, list<Constraint>>
     */
    protected array $constraints = [];

    /**
     * @var array<string, mixed>
     */
    protected array $values = [];

    protected ?\DateTimeInterface $lastModified = null;

    protected ?string $identifier = null;

    protected ?ScriptTraces $traces = null;

    protected ?string $cacheDir = null;

    protected bool $debug = true;

    private ScriptEnvironmentFactory $scriptEnvironmentFactory;

    public function match(RuleScope $scope): bool
    {
        $name = $this->identifier ?? $this->getName();
        $context = [...['scope' => $scope], ...$this->values];
        $lastModified = $this->lastModified ?? $scope->getCurrentTime();

        $script = new Script(
            $name,
            \sprintf('
                {%%- macro evaluate(%1$s) -%%}
                    %2$s
                {%%- endmacro -%%}

                {%%- set var = _self.evaluate(%1$s) -%%}
                {{- var -}}
            ', implode(', ', array_keys($context)), $this->script),
            $lastModified,
        );

        $twigOptions = ['auto_reload' => true];
        if (!$this->debug) {
            $twigOptions['cache'] = new FilesystemCache($this->cacheDir . '/' . $name);
        } else {
            $twigOptions['debug'] = true;
        }
        $script->setTwigOptions($twigOptions);

        $twig = $this->scriptEnvironmentFactory->initEnv($script);
        $hook = new AppScriptConditionHook($scope->getContext());

        try {
            return $this->render($twig, $script, $hook, $name, $context);
        } catch (\Throwable $exception) {
            throw ScriptException::scriptExecutionFailed($hook->getName(), $script->getName(), $exception);
        }
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * @param array<string, list<Constraint>> $constraints
     */
    public function setConstraints(array $constraints): void
    {
        $this->constraints = $constraints;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function assignValues(array $options): self
    {
        $this->values = $options;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function configureDependencies(ContainerInterface $container): void
    {
        $this->scriptEnvironmentFactory = $container->get(ScriptEnvironmentFactory::class);
        $this->traces = $container->get(ScriptTraces::class);
        $this->cacheDir = $container->getParameter('twig.cache');
        $this->debug = $container->getParameter('kernel.debug');
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    private function render(TwigEnvironment $twig, Script $script, Hook $hook, string $name, array $context): bool
    {
        if (!$this->traces) {
            return filter_var(trim($twig->render($name, $context)), \FILTER_VALIDATE_BOOLEAN);
        }

        $match = false;
        $this->traces->trace($hook, $script, static function (Debug $debug) use ($twig, $name, $context, &$match): void {
            $twig->addGlobal('debug', $debug);

            $rendered = $twig->render($name, $context);
            $match = filter_var(trim($rendered), \FILTER_VALIDATE_BOOLEAN);

            $debug->dump($match, 'return');
        });

        return $match;
    }
}
