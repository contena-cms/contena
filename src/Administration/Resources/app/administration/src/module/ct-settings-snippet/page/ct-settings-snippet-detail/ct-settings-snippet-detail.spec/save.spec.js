import { createWrapper, saveMock } from './fixtures';

describe('module/ct-settings-snippet/page/ct-settings-snippet-detail onSave pending delete', () => {
    const deleteMock = jest.fn(() => Promise.resolve());

    beforeEach(() => {
        deleteMock.mockClear();
        saveMock.mockClear();
        Contena.Store.get('session').setCurrentUser({ username: 'admin' });
    });

    it('deletes the DB record when _pendingDelete is set (pending restore, file-overridden)', async () => {
        const wrapper = await createWrapper([], { delete: deleteMock });
        await flushPromises();
        wrapper.vm.snippets = [
            {
                id: 'some-id',
                value: null,
                origin: 'file val',
                _pendingDelete: true,
                author: 'user/admin',
                translationKey: 'account.addressCreateBtn',
                setId: 'a',
            },
            {
                id: null,
                value: null,
                origin: null,
                _pendingDelete: false,
                author: 'user/admin',
                translationKey: 'account.addressCreateBtn',
                setId: 'b',
            },
        ];
        wrapper.vm.isSaveable = true;
        wrapper.vm.onSave();
        expect(deleteMock).toHaveBeenCalledWith('some-id', Contena.Context.api);
        expect(saveMock).not.toHaveBeenCalled();
    });

    it('deletes the DB record when _pendingDelete is set (pending restore, DB-only with empty origin)', async () => {
        const wrapper = await createWrapper([], { delete: deleteMock });
        await flushPromises();
        wrapper.vm.snippets = [
            {
                id: 'some-id',
                value: null,
                origin: '',
                _pendingDelete: true,
                author: 'user/admin',
                translationKey: 'account.addressCreateBtn',
                setId: 'a',
            },
        ];
        wrapper.vm.isSaveable = true;
        wrapper.vm.onSave();
        expect(deleteMock).toHaveBeenCalledWith('some-id', Contena.Context.api);
    });

    it('skips snippet with no DB record and no file value', async () => {
        const wrapper = await createWrapper([], { delete: deleteMock });
        await flushPromises();
        wrapper.vm.snippets = [
            {
                id: null,
                value: null,
                origin: null,
                author: 'user/admin',
                translationKey: 'account.addressCreateBtn',
                setId: 'a',
            },
        ];
        wrapper.vm.isSaveable = true;
        wrapper.vm.onSave();
        expect(deleteMock).not.toHaveBeenCalled();
        expect(saveMock).not.toHaveBeenCalled();
    });
});
