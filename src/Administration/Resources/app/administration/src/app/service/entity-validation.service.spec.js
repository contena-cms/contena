import EntityValidationService from 'src/app/service/entity-validation.service';
import EntityFactory from 'src/core/data/entity-factory.data';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import ErrorResolver from 'src/core/data/error-resolver.data';
import EntityDefinition from 'src/core/data/entity-definition.data';
import EntityDefinitionFactory from 'src/core/factory/entity-definition.factory';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

function createService() {
    return new EntityValidationService(EntityDefinitionFactory, new ChangesetGenerator(), new ErrorResolver());
}

const entityFactory = new EntityFactory();

function createCompleteUser() {
    const user = entityFactory.create('user');

    user.localeId = 'locale-id';
    user.username = 'maxmuster';
    user.password = 'secret';
    user.name = 'Max Mustermann';
    user.email = 'max@example.com';
    user.timeZone = 'UTC';

    return user;
}

describe('src/app/service/entity-validation.service.js', () => {
    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(
            ([
                entityName,
                definitionData,
            ]) => {
                Contena.EntityDefinition.add(entityName, new EntityDefinition(definitionData));
            },
        );
    });

    it('should create a required contena error with the right error code and source pointer', () => {
        const fieldPointer = '/0/name';
        const error = EntityValidationService.createRequiredError(fieldPointer);

        expect(error).toEqual({
            code: EntityValidationService.ERROR_CODE_REQUIRED,
            source: {
                pointer: fieldPointer,
            },
        });
    });

    it('should validate an empty user and report errors', () => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = entityFactory.create('user');

        // validate should return right result
        const isValid = service.validate(testEntity);
        expect(isValid).toBe(false);

        // found errors should match
        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual([
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/localeId' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/username' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/password' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/name' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/email' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/timeZone' },
            },
        ]);
    });

    it('should validate a user with a missing email address', () => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = createCompleteUser();
        testEntity.email = undefined;

        // validate should return right result
        const isValid = service.validate(testEntity);
        expect(isValid).toBe(false);

        // found errors should match
        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual([
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/email' },
            },
        ]);
    });

    it('should validate a complete user and report no errors', () => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = createCompleteUser();

        // validate should return right result
        const isValid = service.validate(testEntity);
        expect(isValid).toBe(true);

        // found errors should match
        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual([]);
    });

    it('should validate a user and report callback errors', () => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = createCompleteUser();

        const customValidator = jest.fn((errors, user) => {
            if (user.mcpAllowlist === undefined) {
                errors.push(EntityValidationService.createRequiredError('/0/mcpAllowlist'));
            }

            return errors;
        });

        const expectedErrors = [
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/mcpAllowlist' },
            },
        ];

        // validate should return right result
        const isValid = service.validate(testEntity, customValidator);
        expect(isValid).toBe(false);

        // found errors should match
        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual(expectedErrors);

        // custom validator should have been called with the right arguments
        expect(customValidator.mock.calls).toHaveLength(1);
        expect(customValidator.mock.calls[0][0]).toEqual(expectedErrors); // initial errors already modified because of array reference
        expect(customValidator.mock.calls[0][1]).toBe(testEntity); // entity
        expect(customValidator.mock.calls[0][2]).toBe(Contena.EntityDefinition.get(testEntity.getEntityName())); // entity definition
        expect(customValidator.mock.results[0].value).toEqual(expectedErrors); // should return the errors
    });

    it('should validate a complete user with ignored fields and report no errors', () => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = createCompleteUser();
        testEntity.email = undefined;

        const ignoreFields = ['email'];

        const isValid = service.validate(testEntity, undefined, ignoreFields);
        expect(isValid).toBe(true);

        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual([]);
    });
});
