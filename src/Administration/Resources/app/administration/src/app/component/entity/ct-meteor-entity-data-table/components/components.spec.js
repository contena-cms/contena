/**
 * @ct-package framework
 */

import CtMeteorEntityDataTableBulkDeleteModal from './ct-meteor-entity-data-table-bulk-delete-modal';
import CtMeteorEntityDataTableDeleteModal from './ct-meteor-entity-data-table-delete-modal';

describe('ct-meteor-entity-data-table components', () => {
    it('exposes local table subcomponents for Vite and Jest resolution', () => {
        expect(CtMeteorEntityDataTableBulkDeleteModal.name).toBe('CtMeteorEntityDataTableBulkDeleteModal');
        expect(CtMeteorEntityDataTableDeleteModal.name).toBe('CtMeteorEntityDataTableDeleteModal');
    });
});
