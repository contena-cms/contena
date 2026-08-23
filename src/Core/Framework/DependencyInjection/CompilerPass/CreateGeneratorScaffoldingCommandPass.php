<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Contena\Core\Framework\Plugin\Command\MakerCommand;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class CreateGeneratorScaffoldingCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $generators = $container->findTaggedServiceIds('contena.scaffold.generator');

        foreach ($generators as $id => $tags) {
            $def = $container->getDefinition($id);
            if ($def->isDeprecated()) {
                continue;
            }

            $class = $container->getParameterBag()->resolveValue($def->getClass());

            if (!is_subclass_of($class, ScaffoldingGenerator::class)) {
                throw DependencyInjectionException::taggedServiceHasWrongType($id, 'contena.scaffold.generator', ScaffoldingGenerator::class);
            }

            $commandDefinition = new ChildDefinition('maker.auto_command.abstract');
            $commandDefinition->setClass(MakerCommand::class);
            $commandDefinition->replaceArgument(0, new Reference($id));

            /** @var class-string $class */
            $class = $def->getClass();
            $ref = new \ReflectionClass($class);

            $tagAttributes = ['command' => 'make:plugin:' . self::asCommand($ref->getShortName())];

            $commandDefinition->addTag('console.command', $tagAttributes);

            $container->setDefinition(
                \sprintf('make.auto_command.%s', self::asSnake($ref->getShortName())),
                $commandDefinition
            );
        }
    }

    private static function asCommand(string $value): string
    {
        $value = str_replace('_', '-', self::asSnake($value));

        return str_replace('-generator', '', $value);
    }

    private static function asSnake(string $value): string
    {
        $snakeCaseConverter = new CamelCaseToSnakeCaseNameConverter();

        return $snakeCaseConverter->normalize($value);
    }
}
