<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Command;

use Bezhanov\Faker\Provider\Commerce;
use Faker\Factory;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\ContentStream\ContentStreamDefinition;
use Contena\Core\Content\Flow\FlowDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Contena\Core\Content\MailTemplate\MailTemplateDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Property\PropertyGroupDefinition;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Demodata\DemodataRequest;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\User\UserDefinition;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'framework:demodata',
    description: 'Generates demo data',
)]
class DemodataCommand extends Command
{
    /**
     * @var array<string, int>
     */
    private array $defaults = [];

    /**
     * @param list<class-string> $requiredClasses
     */
    public function __construct(
        private readonly DemodataService $demodataService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $kernelEnv,
        private readonly array $requiredClasses = [Factory::class, Commerce::class],
    ) {
        parent::__construct();
    }

    public function addDefault(string $name, int $value): void
    {
        $this->defaults[$name] = $value;
    }

    protected function configure(): void
    {
        $this->addOption('blog-attributes', null, InputOption::VALUE_OPTIONAL, 'Blogs attribute count');
        $this->addOption('manufacturer-attributes', null, InputOption::VALUE_OPTIONAL, 'Manufacturer attribute count');
        $this->addOption('order-attributes', null, InputOption::VALUE_OPTIONAL, 'Order attribute count');
        $this->addOption('user-attributes', null, InputOption::VALUE_OPTIONAL, 'User attribute count');
        $this->addOption('media-attributes', null, InputOption::VALUE_OPTIONAL, 'Media attribute count');
        $this->addOption('multiplier', null, InputOption::VALUE_OPTIONAL, 'Applies to all counts');
        $this->addOption('reset-defaults', null, InputOption::VALUE_NONE, 'Set all counts to 0 unless specified');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->kernelEnv !== 'prod') {
            $output->writeln('Demo data command requires the app environment set to production to run. Execute it with: `APP_ENV=prod bin/console framework:demodata`');

            return self::INVALID;
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('Demodata Generator');

        if (!$this->ensureAllDependenciesArePresent($io)) {
            return self::FAILURE;
        }

        $context = Context::createCLIContext();

        $request = new DemodataRequest();

        $request->multiplier = (int) ($input->getOption('multiplier') ?? 1);
        $request->multiplier = max($request->multiplier, 1);

        $request->add(TagDefinition::class, $this->getCount($input, 'tags'));
        $request->add(RuleDefinition::class, $this->getCount($input, 'rules'));
        $request->add(MediaDefinition::class, $this->getCount($input, 'media'));
        $request->add(UserDefinition::class, $this->getCount($input, 'users'));
        $request->add(PropertyGroupDefinition::class, $this->getCount($input, 'properties'));
        $request->add(CategoryDefinition::class, $this->getCount($input, 'categories'));
        $request->add(BlogDefinition::class, $this->getCount($input, 'blogs'));
        $request->add(ContentStreamDefinition::class, $this->getCount($input, 'blog-streams'));
        $request->add(AdminUserDefinition::class, $this->getCount($input, 'users'));
        $request->add(FlowDefinition::class, $this->getCount($input, 'flows'));

        $request->add(
            CustomFieldSetDefinition::class,
            $this->getCount($input, 'attribute-sets'),
            $this->getCustomFieldOptions($input)
        );

        $request->add(MailTemplateDefinition::class, $this->getCount($input, 'mail-template'));
        $request->add(MailHeaderFooterDefinition::class, $this->getCount($input, 'mail-header-footer'));
        $request->add(ChannelDomainDefinition::class, $this->getCount($input, 'sales-channel-domain'));

        $this->eventDispatcher->dispatch(new DemodataRequestCreatedEvent($request, $context, $input));

        $demoContext = $this->demodataService->generate($request, $context, $io);

        $io->table(
            ['Entity', 'Items', 'Time'],
            $demoContext->getTimings()
        );

        $io->info('Run "bin/console dal:refresh:index" to refresh all indices after generating demo data.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function getCustomFieldOptions(InputInterface $input): array
    {
        return [
            'relations' => [
                'blog' => $this->getCount($input, 'blog-attributes'),
                'blog_manufacturer' => $this->getCount($input, 'manufacturer-attributes'),
                'order' => $this->getCount($input, 'order-attributes'),
                'user' => $this->getCount($input, 'user-attributes'),
                'media' => $this->getCount($input, 'media-attributes'),
            ],
        ];
    }

    private function getCount(InputInterface $input, string $name): int
    {
        if ($input->hasOption($name) && $input->getOption($name) !== null) {
            return (int) $input->getOption($name);
        }

        if ($input->getOption('reset-defaults')) {
            return 0;
        }

        return $this->defaults[$name] ?? 0;
    }

    private function ensureAllDependenciesArePresent(SymfonyStyle $io): bool
    {
        foreach ($this->requiredClasses as $class) {
            if (!class_exists($class)) {
                $io->error('Please install composer package "contena/dev-tools" to use the demo-data command.');

                return false;
            }
        }

        return true;
    }
}
