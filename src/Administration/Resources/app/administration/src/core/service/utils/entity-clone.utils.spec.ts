import EntityCollection from 'src/core/data/entity-collection.data';
import Entity from 'src/core/data/entity.data';
import deepCloneWithEntity from './entity-clone.utils';

describe('entity-clone.utils', () => {
    it('deep clones entities and entity collections without their context', () => {
        const originalValue = {
            name: 'Contena',
            user: new Entity('user-1', 'user', {
                username: 'admin',
            } as never),
            mediaCollection: new EntityCollection(
                'demo/media',
                'media',
                {
                    auth: {
                        token: 'secret',
                    },
                } as never,
                null,
                [
                    new Entity('image-1', 'media', {
                        url: 'https://example.com/image.jpg',
                    } as never),
                ],
            ),
        };

        const clonedValue = deepCloneWithEntity(originalValue) as typeof originalValue;

        expect(JSON.stringify(clonedValue)).toEqual(JSON.stringify(originalValue));
        expect(clonedValue.user).not.toBe(originalValue.user);
        expect(clonedValue.mediaCollection).not.toBe(originalValue.mediaCollection);
        expect(clonedValue.mediaCollection[0]).not.toBe(originalValue.mediaCollection[0]);
        expect((originalValue.mediaCollection.context as unknown as { auth: unknown }).auth).toEqual({
            token: 'secret',
        });
        expect((clonedValue.mediaCollection.context as unknown as { auth?: unknown }).auth).toBeUndefined();
    });
});
