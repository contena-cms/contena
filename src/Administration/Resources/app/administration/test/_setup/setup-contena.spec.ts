/**
 * @sw-package framework
 */

import useListing from 'src/app/composables/use-listing';

describe('test/_setup/setup-contena', () => {
    describe('Contena.Composables', () => {
        it('loads a composable when it is first read', () => {
            expect(Contena.Composables.useListing).toBe(useListing);
        });

        it('lets a spec spy on a composable and restore it', () => {
            const spy = jest.spyOn(Contena.Composables, 'useListing');

            expect(Contena.Composables.useListing).toBe(spy);

            spy.mockRestore();

            expect(Contena.Composables.useListing).toBe(useListing);
        });
    });
});
