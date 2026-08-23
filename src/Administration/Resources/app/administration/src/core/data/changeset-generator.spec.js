import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import EntityFactory from 'src/core/data/entity-factory.data';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

const changesetGenerator = new ChangesetGenerator();
const entityFactory = new EntityFactory();

describe('src/core/data/changeset-generator.data.js', () => {
    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(
            ([
                entityName,
                entityDefinition,
            ]) => {
                Contena.EntityDefinition.add(entityName, entityDefinition);
            },
        );
    });

    it('should generate no changes', async () => {
        const testEntity = entityFactory.create('custom_field');

        const { changes } = changesetGenerator.generate(testEntity);

        expect(changes).toBeNull();
    });

    [
        {
            description: 'Change property name',
            entityName: 'custom_field',
            originChanges: { name: 'Microsoft' },
            entityChanges: { name: 'Contena AG' },
            expected: { name: 'Contena AG' },
        },
        {
            description: 'Should create full changeset',
            entityName: 'custom_field',
            originChanges: { name: 'Microsoft' },
            entityChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide',
                    ],
                },
            },
            expected: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide',
                    ],
                },
            },
        },
        {
            description: 'should not return an changeset when origin and draft are identical',
            entityName: 'custom_field',
            originChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide',
                    ],
                },
            },
            entityChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide',
                    ],
                },
            },
            expected: null,
        },
        {
            description:
                'should not return an changeset when origin and draft are identical except the key order in objects',
            entityName: 'custom_field',
            originChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide',
                    ],
                },
            },
            entityChanges: {
                config: {
                    test: [
                        'sum',
                        'add',
                        'divide',
                    ],
                    b: 'bar',
                    a: 'foo',
                },
            },
            expected: null,
        },
        {
            description: 'Should create a changeset when the order in arrays are changing',
            entityName: 'custom_field',
            originChanges: {
                config: {
                    numbers: [
                        1,
                        2,
                        3,
                    ],
                },
            },
            entityChanges: {
                config: {
                    numbers: [
                        2,
                        1,
                        3,
                    ],
                },
            },
            expected: {
                config: {
                    numbers: [
                        2,
                        1,
                        3,
                    ],
                },
            },
        },
        {
            description:
                'Should create a changeset when the order in arrays are changing. In combination with object key order changes.',
            entityName: 'custom_field',
            originChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'First',
                        'Second',
                        'Third',
                    ],
                },
            },
            entityChanges: {
                config: {
                    test: [
                        'Second',
                        'First',
                        'Third',
                    ],
                    b: 'bar',
                    a: 'foo',
                },
            },
            expected: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'Second',
                        'First',
                        'Third',
                    ],
                },
            },
        },
        {
            description: 'Should be able to null an array value',
            entityName: 'custom_field',
            originChanges: {
                config: {
                    numbers: [
                        1,
                        2,
                        3,
                    ],
                },
            },
            entityChanges: {
                config: {
                    numbers: null,
                },
            },
            expected: {
                config: {
                    numbers: null,
                },
            },
        },
        {
            description: 'Should be able to null some scalar value',
            entityName: 'custom_field',
            originChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'System',
                            second: 'config',
                        },
                        sum: 'mary',
                    },
                },
            },
            entityChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'System',
                            second: 'config',
                        },
                        sum: null,
                    },
                },
            },
            expected: {
                config: {
                    test: {
                        foo: {
                            bar: 'System',
                            second: 'config',
                        },
                        sum: null,
                    },
                },
            },
        },
        {
            description: 'Should create a changeset the json field when a field was removed completely',
            entityName: 'custom_field',
            originChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'System',
                            second: 'config',
                        },
                        animals: [
                            'dog',
                            'cat',
                            'bird',
                        ],
                    },
                },
            },
            entityChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'System',
                            second: 'config',
                        },
                    },
                },
            },
            expected: {
                config: {
                    test: {
                        foo: {
                            bar: 'System',
                            second: 'config',
                        },
                    },
                },
            },
        },
        {
            description: 'Should create a changeset when the json root is an object which is resetted to null',
            entityName: 'custom_field',
            originChanges: {
                config: {},
            },
            entityChanges: {
                config: null,
            },
            expected: {
                config: null,
            },
        },
        {
            description: 'Should create a changeset when the json root is an array which is resetted to null',
            entityName: 'custom_field',
            originChanges: {
                config: [],
            },
            entityChanges: {
                config: null,
            },
            expected: {
                config: null,
            },
        },
        {
            description: 'Should create a changeset when the json root is an array and the order changes',
            entityName: 'custom_field',
            originChanges: {
                config: [
                    1,
                    2,
                    3,
                ],
            },
            entityChanges: {
                config: [
                    2,
                    1,
                    3,
                ],
            },
            expected: {
                config: [
                    2,
                    1,
                    3,
                ],
            },
        },
        {
            description: 'Should not create a changeset when the json root is an object and the order changes',
            entityName: 'custom_field',
            originChanges: {
                config: {
                    a: 'a',
                    b: 'b',
                    c: 'c',
                },
            },
            entityChanges: {
                config: {
                    a: 'a',
                    c: 'c',
                    b: 'b',
                },
            },
            expected: null,
        },
    ].forEach(({ description, entityChanges, originChanges, expected, entityName }) => {
        it(`${description}`, async () => {
            const testEntity = entityFactory.create(entityName);

            Object.entries(originChanges).forEach(
                ([
                    key,
                    value,
                ]) => {
                    testEntity.getDraft()[key] = value;
                    testEntity.getOrigin()[key] = value;
                },
            );

            Object.entries(entityChanges).forEach(
                ([
                    key,
                    value,
                ]) => {
                    testEntity[key] = value;
                },
            );

            const { changes } = changesetGenerator.generate(testEntity);

            if (changes !== null && changes.hasOwnProperty('id')) {
                delete changes.id;
            }
            if (changes !== null && changes.hasOwnProperty('id')) {
                delete changes.versionId;
            }

            expect(changes).toEqual(expected);
        });
    });
});
