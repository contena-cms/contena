/**
 * @sw-package framework
 *
 * Proves the `contena:*` modules are usable from a spec, and that what they hand out is the very object
 * the global `Contena` holds rather than a copy of it.
 */

import { createId, object } from 'contena:utils';
import { warn, error } from 'contena:utils/debug';
import debugNamespace from 'contena:utils/debug';
import { Criteria, EntityCollection } from 'contena:data';
import CriteriaClass from 'contena:data/Criteria';
import swFormFieldMixin from 'contena:mixins/sw-form-field';
import ruleContainerMixin from 'contena:mixins/ruleContainer';
import useNotificationStore from 'contena:stores/notification';
import useSystemStore from 'contena:stores/system';
import composables, { useListing } from 'contena:composables';
import useListingDefault from 'contena:composables/useListing';
import directUseListing from 'src/app/composables/use-listing';

describe('contena:* virtual modules', () => {
    describe('root imports', () => {
        it('export the members of their branch', () => {
            expect(createId).toBe(Contena.Utils.createId);
            expect(object).toBe(Contena.Utils.object);
            expect(Criteria).toBe(Contena.Data.Criteria);
            expect(EntityCollection).toBe(Contena.Data.EntityCollection);
        });

        it('export the composables the Administration itself imports', () => {
            expect(useListing).toBe(directUseListing);
            expect(composables.useListing).toBe(Contena.Composables.useListing);
        });

        it('export working members', () => {
            expect(createId()).toHaveLength(32);
            expect(new Criteria(1, 25).limit).toBe(25);
        });
    });

    describe('subpaths', () => {
        it('export the members of one namespace, and the namespace as default', () => {
            expect(warn).toBe(Contena.Utils.debug.warn);
            expect(error).toBe(Contena.Utils.debug.error);
            expect(debugNamespace.warn).toBe(Contena.Utils.debug.warn);
        });

        it('export a DAL class as default', () => {
            expect(CriteriaClass).toBe(Contena.Data.Criteria);
        });

        it('export a composable as default', () => {
            expect(useListingDefault).toBe(directUseListing);
        });

        it('export a registered mixin as default', () => {
            expect(swFormFieldMixin).toBe(Contena.Mixin.getByName('sw-form-field'));
        });

        it('take the registry key verbatim, camelCase included', () => {
            expect(ruleContainerMixin).toBe(Contena.Mixin.getByName('ruleContainer'));
        });

        it('export a store as a composable, resolved per call', () => {
            expect(typeof useNotificationStore).toBe('function');
            expect(useNotificationStore()).toBe(Contena.Store.get('notification'));
            expect(useSystemStore()).toBe(Contena.Store.get('system'));
        });
    });
});
