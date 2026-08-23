<?php declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Content/Media/Thumbnail/ThumbnailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Content\\Media\\TypeDetector\\TypeDetector::detect() should return Contena\\Core\\Content\\Media\\MediaType\\MediaType but returns Contena\\Core\\Content\\Media\\MediaType\\MediaType|null.',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/TypeDetector/TypeDetector.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/Environment/EnvironmentHelper.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Api\\Acl\\Event\\AclGetAdditionalPrivilegesEvent::__construct() has parameter $privileges with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Acl/Event/AclGetAdditionalPrivilegesEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Api\\Acl\\Event\\AclGetAdditionalPrivilegesEvent::getPrivileges() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Acl/Event/AclGetAdditionalPrivilegesEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Api\\Acl\\Event\\AclGetAdditionalPrivilegesEvent::setPrivileges() has parameter $privileges with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Acl/Event/AclGetAdditionalPrivilegesEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/ApiController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/EventListener/JsonRequestTransformerListener.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Api\\Exception\\IncompletePrimaryKeyException::__construct() has parameter $primaryKeyFields with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Exception/IncompletePrimaryKeyException.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Api\\Exception\\ResourceNotFoundException::__construct() has parameter $primaryKey with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Exception/ResourceNotFoundException.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Response/ResponseFactoryRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 7,
    'path' => __DIR__ . '/src/Core/Framework/Api/Serializer/JsonApiDecoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Api/Serializer/JsonApiEncodingResult.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/CustomField/Xml/CustomFieldTypes/CustomFieldTypeFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/CompiledFieldCollection.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/FieldAccessorBuilder/DefaultFieldAccessorBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/ManyToManyAssociationFieldResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/ManyToOneAssociationFieldResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/TranslationFieldResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityProtection/EntityProtectionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\EntityProtection\\WriteProtection::getAllowedScopes() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityProtection/WriteProtection.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityTranslationDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Event\\EntityLoadedContainerEvent::__construct() has parameter $events with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Event/EntityLoadedContainerEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Field\\Flag\\Runtime::__construct() has parameter $dependsOn with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Field/Flag/Runtime.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Field\\Flag\\Runtime::getDepends() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Field/Flag/Runtime.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Field\\StateMachineStateField::__construct() has parameter $allowedWriteScopes with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Field/StateMachineStateField.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Field\\StateMachineStateField::getAllowedWriteScopes() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Field/StateMachineStateField.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method is() on Contena\\Core\\Framework\\DataAbstractionLayer\\Field\\Field|null.',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/AbstractFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/AbstractFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/StateMachineStateFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/TenantFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\FieldVisibility::filterInvisible() has parameter $data with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldVisibility.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\FieldVisibility::filterInvisible() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldVisibility.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Indexing\\TreeUpdaterBag::addEntity() has parameter $entity with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Indexing/TreeUpdaterBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Indexing\\TreeUpdaterBag::getEntity() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Indexing/TreeUpdaterBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Property Contena\\Core\\Framework\\DataAbstractionLayer\\Indexing\\TreeUpdaterBag::$entities type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Indexing/TreeUpdaterBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Property Contena\\Core\\Framework\\DataAbstractionLayer\\Indexing\\TreeUpdaterBag::$updated type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Indexing/TreeUpdaterBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/MappingEntityDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/ApiCriteriaValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/Filter/MultiFilter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/Parser/AggregationParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 12,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/Parser/QueryStringParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Version\\Aggregate\\VersionCommitData\\VersionCommitDataCollection::filterByEntityPrimary() has parameter $primary with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Version/Aggregate/VersionCommitData/VersionCommitDataCollection.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\CloneBehavior::__construct() has parameter $overwrites with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/CloneBehavior.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\CloneBehavior::getOverwrites() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/CloneBehavior.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/Command/WriteCommandQueue.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\PrimaryKeyBag::add() has parameter $primaryKey with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/PrimaryKeyBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\PrimaryKeyBag::addExistenceState() has parameter $primaryKey with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/PrimaryKeyBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\PrimaryKeyBag::addExistenceState() has parameter $state with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/PrimaryKeyBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\PrimaryKeyBag::getCacheKey() has parameter $primaryKey with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/PrimaryKeyBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\PrimaryKeyBag::getExistenceState() has parameter $primaryKey with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/PrimaryKeyBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\PrimaryKeyBag::getExistenceState() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/PrimaryKeyBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\PrimaryKeyBag::getPrimaryKeys() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/PrimaryKeyBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\PrimaryKeyBag::hasExistence() has parameter $primaryKey with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/PrimaryKeyBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Property Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\PrimaryKeyBag::$existences type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/PrimaryKeyBag.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Property Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\Validation\\Validator::$data type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/Validation/Validator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DataAbstractionLayer\\Write\\Validation\\WriteCommandExceptionEvent::getCommands() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/Validation/WriteCommandExceptionEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/FeatureFlagCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\DependencyInjection\\FrameworkExtension::addContenaConfig() has parameter $options with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/FrameworkExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Feature.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Parameter\\AdditionalBundleParameters::__construct() has parameter $kernelParameters with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Parameter/AdditionalBundleParameters.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Parameter\\AdditionalBundleParameters::getKernelParameters() return type has no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Parameter/AdditionalBundleParameters.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Parameter\\AdditionalBundleParameters::setKernelParameters() has parameter $kernelParameters with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Parameter/AdditionalBundleParameters.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Plugin\\Command\\Lifecycle\\AbstractPluginLifecycleCommand::parsePluginArgument() has parameter $arguments with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/Command/Lifecycle/AbstractPluginLifecycleCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $elements of class Contena\\Core\\Framework\\Plugin\\PluginCollection constructor expects iterable<Contena\\Core\\Framework\\Plugin\\PluginEntity>, array<int, Contena\\Core\\Framework\\Plugin\\PluginEntity|null> given.',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/Command/Lifecycle/AbstractPluginLifecycleCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/PluginService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/Requirement/RequirementExceptionStack.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/Util/PluginFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/RateLimiter/Policy/TimeBackoffLimiter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Routing/RouteScopeRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/Struct/Serializer/StructNormalizer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Struct/Struct.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Validation\\DataValidator::getViolations() has parameter $data with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Validation/DataValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Validation\\DataValidator::validate() has parameter $data with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Validation/DataValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Validation\\DataValidator::validateListDefinitions() has parameter $data with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Validation/DataValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Validation\\DataValidator::validateProperties() has parameter $data with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Validation/DataValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method Contena\\Core\\Framework\\Validation\\DataValidator::validateSubDefinitions() has parameter $data with no value type specified in iterable type array.',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Validation/DataValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Installer/Controller/InstallerController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Installer/InstallerKernel.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Maintenance/System/Command/SystemSetupCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/Language/TranslationValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/Snippet/Filter/EmptySnippetFilter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/Snippet/Subscriber/CustomFieldSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Expected domain exception class Contena\\Core\\System\\User\\UserException, got Contena\\Core\\Framework\\Routing\\RoutingException',
    'identifier' => 'contena.domainException',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/System/User/Api/UserValidationController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Expected domain exception class Contena\\Core\\System\\Channel\\ChannelException, got Contena\\Core\\Framework\\Routing\\RoutingException',
    'identifier' => 'contena.domainException',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/Channel/Channel/ChannelApiInfoController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Construct empty() is not allowed. Use more strict comparison.',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Test/Integration/Helper/MailEventListener.php',
];

$ignoreErrors = array_values(array_filter(
    $ignoreErrors,
    static fn (array $error): bool => !isset($error['path']) || file_exists($error['path'])
));

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
