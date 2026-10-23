<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Blog\DataAbstractionLayer\StatesUpdater;
use Contena\Core\Content\ContentStream\ContentStreamDefinition;
use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Contena\Core\Content\MailTemplate\MailTemplateDefinition;
use Contena\Core\Content\Media\File\FileNameProvider;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Contena\Core\Framework\Demodata\Command\DemodataCommand;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Demodata\Generator\AdminUserGenerator;
use Contena\Core\Framework\Demodata\Generator\BlogGenerator;
use Contena\Core\Framework\Demodata\Generator\CategoryGenerator;
use Contena\Core\Framework\Demodata\Generator\ChannelDomainGenerator;
use Contena\Core\Framework\Demodata\Generator\ContentStreamGenerator;
use Contena\Core\Framework\Demodata\Generator\CustomFieldGenerator;
use Contena\Core\Framework\Demodata\Generator\FlowGenerator;
use Contena\Core\Framework\Demodata\Generator\MailHeaderFooterGenerator;
use Contena\Core\Framework\Demodata\Generator\MailTemplateGenerator;
use Contena\Core\Framework\Demodata\Generator\MediaGenerator;
use Contena\Core\Framework\Demodata\Generator\PropertyGroupGenerator;
use Contena\Core\Framework\Demodata\Generator\RuleGenerator;
use Contena\Core\Framework\Demodata\Generator\TagGenerator;
use Contena\Core\Framework\Demodata\Generator\UserGenerator;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\User\UserDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DemodataCommand::class)
        ->args([
            service(DemodataService::class),
            service('event_dispatcher'),
            param('kernel.environment'),
        ])
        ->tag('console.command');

    $services->set(DemodataService::class)
        ->args([
            tagged_iterator('contena.cnmodata_generator'),
            param('kernel.project_dir'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ]);

    // Generators
    // The option-* tag attributes exist in dashed AND underscore form because the XML loader
    // emitted both spellings; both are kept so the compiled container stays identical.
    $services->set(RuleGenerator::class)
        ->args([
            service('rule.repository'),
            service(EntityWriter::class),
            service('payment_method.repository'),
            service('shipping_method.repository'),
            service(RuleDefinition::class),
            service(ClockInterface::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'rules', 'option-default' => 25, 'option_name' => 'rules', 'option_default' => 25]);

    $services->set(UserGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(Connection::class),
            service('user_group.repository'),
            service(NumberRangeValueGeneratorInterface::class),
            service(UserDefinition::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'users', 'option-default' => 60, 'option_name' => 'users', 'option_default' => 60]);

    $services->set(PropertyGroupGenerator::class)
        ->args([
            service('property_group.repository'),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'properties', 'option-default' => 10, 'option-description' => 'Property group count (option count rand(30-300))', 'option_name' => 'properties', 'option_default' => 10, 'option_description' => 'Property group count (option count rand(30-300))']);

    $services->set(CategoryGenerator::class)
        ->args([
            service('category.repository'),
            service('cms_page.repository'),
            service(Connection::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'categories', 'option-default' => 10, 'option_name' => 'categories', 'option_default' => 10]);


    $services->set(TagGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(TagDefinition::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'tags', 'option-default' => 50, 'option_name' => 'tags', 'option_default' => 50]);


    $services->set(BlogGenerator::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
            service(InheritanceUpdater::class),
            service(StatesUpdater::class)->nullOnInvalid(),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'blogs', 'option-default' => 1000, 'option_name' => 'blogs', 'option_default' => 1000]);


    $services->set(FlowGenerator::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
            service(BusinessEventCollector::class),
            service(FlowActionCollector::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'flows', 'option-default' => 0, 'option_name' => 'flows', 'option_default' => 0]);

    $services->set(MediaGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(FileSaver::class),
            service(FileNameProvider::class),
            service('media_default_folder.repository'),
            service('media_folder.repository'),
            service(MediaDefinition::class),
            service(Connection::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'media', 'option-default' => 300, 'option_name' => 'media', 'option_default' => 300]);

    $services->set(ContentStreamGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(ContentStreamDefinition::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'blog-streams', 'option-default' => 10, 'option_name' => 'blog-streams', 'option_default' => 10]);


    $services->set(CustomFieldGenerator::class)
        ->args([
            service('custom_field_set.repository'),
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'attribute-sets', 'option-default' => 4, 'option-description' => 'CustomField set count', 'option_name' => 'attribute-sets', 'option_default' => 4, 'option_description' => 'CustomField set count']);

    $services->set(MailTemplateGenerator::class)
        ->args([
            service(EntityWriter::class),
            service('mail_template_type.repository'),
            service(MailTemplateDefinition::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'mail-template', 'option-default' => 10, 'option_name' => 'mail-template', 'option_default' => 10]);

    $services->set(MailHeaderFooterGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(MailHeaderFooterDefinition::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'mail-header-footer', 'option-default' => 3, 'option-description' => 'Mail header/footer count', 'option_name' => 'mail-header-footer', 'option_default' => 3, 'option_description' => 'Mail header/footer count']);

    $services->set(ChannelDomainGenerator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'sales-channel-domain', 'option-default' => 1, 'option_name' => 'sales-channel-domain', 'option_default' => 1]);

    $services->set(AdminUserGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(AdminUserDefinition::class),
            service('language.repository'),
        ])
        ->tag('contena.cnmodata_generator', ['option-name' => 'users', 'option-default' => 0, 'option_name' => 'users', 'option_default' => 0]);

};
