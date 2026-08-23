<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Binding\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\ContentSystem\Binding\DefaultBindingSpecificationSynthesizer;
use Contena\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Contena\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Contena\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Contena\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeSourceDirectory;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Contena\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(YamlBindingSpecificationLoader::class)]
class YamlBindingSpecificationLoaderTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yaml-binding-specification-loader-test-' . getmypid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->tempDir);
    }

    #[TestDox('loads an inline binding whose implicit type is resolved from the file path and directory prefix')]
    public function testLoadsInlineBindingWithImplicitTypeFromPathAndPrefix(): void
    {
        // A file media/image.yaml under prefix "Sw" yields type "CT:Media:Image" (ElementTypeNameResolver's
        // kebab-to-PascalCase, colon-joined, prefixed rule).
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "meta:\n  label: Image\nbindings:\n  image-binding:\n    label: \"Image binding\"\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $specifications = $loader->load();

        static::assertCount(1, $specifications);
        static::assertSame('image-binding', $specifications[0]->id());
        static::assertSame('CT:Media:Image', $specifications[0]->type());
        static::assertSame('core', $specifications[0]->source());
    }

    #[TestDox('allows two different sources to each ship the same bare id without throwing')]
    public function testAllowsSameBareIdAcrossDifferentSources(): void
    {
        $dirA = $this->tempDir . '/a';
        $dirB = $this->tempDir . '/b';
        mkdir($dirA . '/media', 0777, true);
        mkdir($dirB . '/media', 0777, true);
        file_put_contents($dirA . '/media/image.yaml', "bindings:\n  shared:\n    label: a\n");
        file_put_contents($dirB . '/media/image.yaml', "bindings:\n  shared:\n    label: b\n");

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source-a', $dirA, 'Sw'),
            new ElementTypeSourceDirectory('source-b', $dirB, 'Sw'),
        ]);

        $specifications = $loader->load();

        static::assertCount(2, $specifications);
        static::assertSame(['source-a', 'source-b'], array_map(static fn ($specification) => $specification->source(), $specifications));
    }

    #[TestDox('skips an element-type file that carries no bindings section')]
    public function testSkipsTypeFileWithoutBindingsSection(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "meta:\n  label: Image\nproperties:\n  mediaId:\n    type: string\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns an empty array for a non-existent directory')]
    public function testReturnsEmptyForMissingDirectory(): void
    {
        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', '/path/does/not/exist', 'Sw')]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns an empty array for a directory that exists but holds no YAML files')]
    public function testReturnsEmptyForDirectoryWithoutYaml(): void
    {
        file_put_contents($this->tempDir . '/notes.txt', 'not a yaml file');

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('loads an inline id at exactly the maximum length of 255 characters')]
    public function testLoadsIdAtExactlyMaxLength(): void
    {
        $id = str_repeat('a', 255);
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  {$id}:\n    label: x\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $specifications = $loader->load();

        static::assertCount(1, $specifications);
        static::assertSame($id, $specifications[0]->id());
    }

    #[TestDox('round-trips a synthesized default specification through load preserving source-qualified id, type, label, canonicalized resolves, and isDefault()')]
    public function testSynthesizedDefaultRoundTripsLoad(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents(
            $this->tempDir . '/media/image.yaml',
            "meta:\n  label: Image\nproperties:\n  media:\n    type: Contena\\Core\\Content\\Media\\MediaEntity\n    resolvedBy: mediaId\n",
        );

        $loader = new YamlBindingSpecificationLoader(
            [new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')],
            new ElementTypeNameResolver(),
            new DefaultBindingSpecificationSynthesizer(),
            new BindingSpecificationSerializer(),
            $this->canonicalizerForSynthesisScenarios(),
            $this->passingValidator(),
        );

        $specifications = $loader->load();

        static::assertCount(1, $specifications);
        $specification = $specifications[0];

        static::assertSame('CT:Media:Image', $specification->id());
        static::assertSame('CT:Media:Image', $specification->type());
        static::assertSame('Image', $specification->label());
        static::assertSame('core:CT:Media:Image', $specification->qualifiedId());
        static::assertTrue($specification->isDefault());
        static::assertSame([], $specification->inputs());
        static::assertArrayHasKey('media', $specification->resolves());
        static::assertSame('entity', $specification->resolves()['media']->loader);
        static::assertSame(['entity' => 'media', 'property' => 'mediaId'], $specification->resolves()['media']->config);
    }

    #[DataProvider('throwsOnLoadFailureProvider')]
    #[TestDox('throws bindingSpecificationLoadFailed for $_dataName')]
    public function testThrowsOnLoadFailure(string $relativePath, string $body, string $reason): void
    {
        $fullPath = $this->tempDir . '/' . $relativePath;
        mkdir(\dirname($fullPath), 0777, true);
        file_put_contents($fullPath, $body);

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationLoadFailed($fullPath, $reason)
        );

        $loader->load();
    }

    #[TestDox('reports the duplicate id even when the duplicate entry would also fail shape validation')]
    public function testDuplicateIdIsReportedBeforeEntryShapeValidation(): void
    {
        // *.yaml files are collected before *.yml files (two findFiles passes), so image.yaml deterministically
        // registers "shared" first; banner.yml's duplicate must surface as the duplicate error, not as the
        // shape error its non-map entry would otherwise produce.
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  shared:\n    label: a\n");
        file_put_contents($this->tempDir . '/media/banner.yml', "bindings:\n  shared: not-a-map\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationDuplicate('shared', 'image.yaml', 'banner.yml'));

        $loader->load();
    }

    #[TestDox('throws when the same source ships the same id across two directories')]
    public function testThrowsOnSameSourceAcrossDifferentDirectories(): void
    {
        $dirA = $this->tempDir . '/a';
        $dirB = $this->tempDir . '/b';
        mkdir($dirA . '/media', 0777, true);
        mkdir($dirB . '/hero', 0777, true);
        file_put_contents($dirA . '/media/image.yaml', "bindings:\n  shared:\n    label: a\n");
        file_put_contents($dirB . '/hero/banner.yaml', "bindings:\n  shared:\n    label: b\n");

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source-a', $dirA, 'Sw'),
            new ElementTypeSourceDirectory('source-a', $dirB, 'Sw'),
        ]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationDuplicate('shared', 'source-a', 'source-a')
        );

        $loader->load();
    }

    #[DataProvider('rejectsInlineEntryWithForbiddenKeyProvider')]
    #[TestDox('rejects an inline entry that declares $_dataName')]
    public function testRejectsInlineEntryWithForbiddenKey(string $forbiddenKey, string $yamlValue, string $expectedMessage): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  image-binding:\n    {$forbiddenKey}: {$yamlValue}\n    label: x\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationCanonicalizationFailed('image-binding', $expectedMessage));

        $loader->load();
    }

    #[TestDox('fails hard on unparsable YAML, surfacing the file path')]
    public function testFailsOnUnparsableYaml(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/broken.yaml', "id: \"unterminated\n  bad: [");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/Invalid YAML syntax/');
        $this->expectExceptionMessageMatches('/' . preg_quote($this->tempDir . '/media/broken.yaml', '/') . '/');

        $loader->load();
    }

    #[TestDox('throws bindingSpecificationsInvalid, surfacing the violation path, when the validator reports a problem')]
    public function testFailsValidationForMalformedSpecification(): void
    {
        // The implicit type is registered (stub) and the entry carries no sugared resolves, so canonicalization
        // is a no-op.
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  broken:\n    label: x\n");

        // Stub the validator to report one violation: this tests the loader's throw-on-violations wiring
        // (it surfaces the violation path via bindingSpecificationsInvalid). The real constraint that would
        // produce such a violation is covered by the validator's own tests.
        $failing = static::createStub(ValidatorInterface::class);
        $failing->method('validate')->willReturn(new ConstraintViolationList([
            new ConstraintViolation('resolves entry "image" must declare a non-blank "loader"', null, [], null, 'bindings[broken].resolves[image].loader', null),
        ]));

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')], $failing);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/bindings\[broken\]\.resolves\[image\]\.loader/');

        $loader->load();
    }

    #[TestDox('rejects an authored bindings: id equal to the containing file\'s implicit type name as a reserved id')]
    public function testRejectsAuthoredIdEqualToImplicitTypeName(): void
    {
        // The type name is reserved unconditionally, independent of whether the file synthesizes a default:
        // this fixture declares no properties at all, so it synthesizes nothing, yet the reserved-id guard
        // still fires.
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  \"CT:Media:Image\":\n    label: x\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $path = $this->tempDir . '/media/image.yaml';
        $this->expectExceptionObject(ContentSystemException::bindingSpecificationReservedId('CT:Media:Image', 'CT:Media:Image', $path));

        $loader->load();
    }

    #[TestDox('reports a duplicate when a resolvedBy file (.yaml) synthesizes the default id first and an authored entry (.yml) then collides with it')]
    public function testSynthesizedDefaultThenAuthoredCollisionReportsDuplicate(): void
    {
        // *.yaml files precede *.yml files in the loader's two-pass listing, so the resolvedBy file registers the
        // synthesized "CT:Media:Image" default first; the authored entry in the .yml file then collides in the
        // bindings loop (the $seenIds[$id] duplicate check). The authored file's own implicit type is "CT:Hero:Banner",
        // so the reserved-id check ($id === implicitType) passes and the duplicate check is what fires.
        mkdir($this->tempDir . '/media', 0777, true);
        mkdir($this->tempDir . '/hero', 0777, true);
        file_put_contents(
            $this->tempDir . '/media/image.yaml',
            "properties:\n  media:\n    type: Contena\\Core\\Content\\Media\\MediaEntity\n    resolvedBy: mediaId\n",
        );
        file_put_contents($this->tempDir . '/hero/banner.yml', "bindings:\n  \"CT:Media:Image\":\n    label: x\n");

        $loader = new YamlBindingSpecificationLoader(
            [new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')],
            new ElementTypeNameResolver(),
            new DefaultBindingSpecificationSynthesizer(),
            new BindingSpecificationSerializer(),
            $this->canonicalizerForSynthesisScenarios(),
            $this->passingValidator(),
        );

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationDuplicate('CT:Media:Image', 'image.yaml', 'banner.yml'));

        $loader->load();
    }

    #[TestDox('reports a duplicate when an authored entry (.yaml) registers the id first and a resolvedBy file (.yml) then synthesizes the same default id')]
    public function testAuthoredIdThenSynthesizedDefaultCollisionReportsDuplicate(): void
    {
        // The authored file is *.yaml, so it registers "CT:Media:Image" first (its own implicit type "CT:Hero:Banner"
        // clears the reserved-id check); the resolvedBy file is *.yml, processed second, and its synthesized default
        // collides in the synthesized branch (the $seenIds[$implicitType] check) before that entry is canonicalized.
        mkdir($this->tempDir . '/media', 0777, true);
        mkdir($this->tempDir . '/hero', 0777, true);
        file_put_contents($this->tempDir . '/hero/banner.yaml', "bindings:\n  \"CT:Media:Image\":\n    label: x\n");
        file_put_contents(
            $this->tempDir . '/media/image.yml',
            "properties:\n  media:\n    type: Contena\\Core\\Content\\Media\\MediaEntity\n    resolvedBy: mediaId\n",
        );

        $loader = new YamlBindingSpecificationLoader(
            [new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')],
            new ElementTypeNameResolver(),
            new DefaultBindingSpecificationSynthesizer(),
            new BindingSpecificationSerializer(),
            $this->canonicalizerForSynthesisScenarios(),
            $this->passingValidator(),
        );

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationDuplicate('CT:Media:Image', 'banner.yaml', 'image.yml'));

        $loader->load();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function throwsOnLoadFailureProvider(): iterable
    {
        yield 'blank id' => ['media/image.yaml', "bindings:\n  \"\":\n    label: x\n", 'missing or empty "id"'];
        // A numeric YAML key decodes to a PHP int map key, so assertValidId sees a non-string id.
        yield 'non-string id' => ['media/image.yaml', "bindings:\n  123:\n    label: x\n", 'missing or empty "id"'];
        yield 'id exceeds max length' => ['media/image.yaml', "bindings:\n  " . str_repeat('a', 256) . ":\n    label: x\n", 'id exceeds the maximum length of 255 characters'];
        yield 'scalar body' => ['media/scalar.yaml', 'just a string', 'YAML file must contain an array/map, got string'];
        yield 'bindings section is not a map' => ['media/image.yaml', "bindings: not-a-map\n", 'the "bindings" section must be a map of specification id to entry, got string'];
        yield 'bindings entry is not a map' => ['media/image.yaml', "bindings:\n  image-binding: not-a-map\n", 'the "bindings" entry "image-binding" must be a map, got string'];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function rejectsInlineEntryWithForbiddenKeyProvider(): iterable
    {
        yield 'explicit type' => ['type', 'CT:Media:Image', 'an inline binding entry must not declare "type"; the type is implicit from the containing element-type file.'];
        yield 'explicit id' => ['id', 'something-else', 'an inline binding entry must not declare "id"; the map key is the id.'];
    }

    /**
     * @param list<ElementTypeSourceDirectory> $directories
     */
    private function createLoader(array $directories, ?ValidatorInterface $validator = null): YamlBindingSpecificationLoader
    {
        // The loader validates the decoded DTO collection through its injected validator. These tests cover
        // loading MECHANICS (implicit type, dedup, file handling), not the constraints themselves, so a stub
        // validator is injected: it sidesteps the collection's dep-injected TypeConsistentBindingSpecification
        // (whose validator the default no-arg factory cannot build) and the fixtures' unregistered types. The real
        // structural and semantic validation is covered by their own dedicated tests.
        return new YamlBindingSpecificationLoader(
            $directories,
            new ElementTypeNameResolver(),
            new DefaultBindingSpecificationSynthesizer(),
            new BindingSpecificationSerializer(),
            $this->canonicalizer(),
            $validator ?? $this->passingValidator(),
        );
    }

    private function canonicalizer(): BindingSpecificationCanonicalizer
    {
        // The type registry accepts every type and the fixtures carry no sugared resolves, so the map resolver
        // and DAL registries are never reached during the load (canonicalization has its own dedicated test).
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturn(true);
        $typeRegistry->method('get')->willReturn(new ContentSystemElementTypeSpecification(
            'media-gallery',
            'Media gallery',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [],
            [],
        ));

        $mapResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $mapResolver->method('resolve')->willReturn(new ContentSystemDataLoaderMap([], []));

        return new BindingSpecificationCanonicalizer(
            $typeRegistry,
            $mapResolver,
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(ChannelDefinitionInstanceRegistry::class),
        );
    }

    /**
     * A real tier-A expansion for "CT:Media:Image" (a declared MediaEntity reference, resolved to the "media"
     * entity name) plus an empty "CT:Hero:Banner" for the reserved-id/cross-file-collision fixtures, which
     * author no resolves at all. Both names are needed because the two collision tests swap the .yaml/.yml roles:
     * the .yaml (always listed before the .yml) is the file that reaches full canonicalization, and it declares a
     * different type in each — the synthesized "CT:Media:Image" in one, the authored file's own "CT:Hero:Banner"
     * in the other. The colliding .yml throws at the duplicate check before it canonicalizes.
     */
    private function canonicalizerForSynthesisScenarios(): BindingSpecificationCanonicalizer
    {
        $types = [
            'CT:Media:Image' => new ContentSystemElementTypeSpecification(
                'CT:Media:Image',
                'Image',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                ['media' => new PropertySpecification('media', new PropertyType(MediaEntity::class, false, null, null), false, '', '', null)],
                [],
            ),
            'CT:Hero:Banner' => new ContentSystemElementTypeSpecification(
                'CT:Hero:Banner',
                'Banner',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [],
                [],
            ),
        ];

        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturnCallback(static fn (string $name): bool => isset($types[$name]));
        $typeRegistry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $types[$name]);

        $mapResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $mapResolver->method('resolve')->willReturn(new ContentSystemDataLoaderMap([], []));

        $mediaDefinition = static::createStub(EntityDefinition::class);
        $mediaDefinition->method('getEntityName')->willReturn('media');
        $mediaDefinition->method('getEntityClass')->willReturn(MediaEntity::class);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('getDefinitions')->willReturn(['media' => $mediaDefinition]);

        $channelRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $channelRegistry->method('getChannelDefinitions')->willReturn([]);

        return new BindingSpecificationCanonicalizer($typeRegistry, $mapResolver, $definitionRegistry, $channelRegistry);
    }

    private function passingValidator(): ValidatorInterface
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        return $validator;
    }
}
