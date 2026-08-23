import DataDictionaryService from './data-dictionary.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

describe('DataDictionaryService', () => {
    it('maps items to reusable options and caches identical requests', async () => {
        const search = jest.fn().mockResolvedValue([
            { id: 'one', code: 'male', label: 'Male', active: true },
            { id: 'two', code: 'undisclosed', label: null, active: true },
        ]);
        const repositoryFactory = {
            create: jest.fn().mockReturnValue({ search }),
        } as unknown as RepositoryFactory;
        const service = new DataDictionaryService(repositoryFactory);

        await expect(service.getOptions('core.gender')).resolves.toEqual([
            { value: 'male', code: 'male', label: 'Male' },
            { value: 'undisclosed', code: 'undisclosed', label: 'undisclosed' },
        ]);
        await service.getOptions('core.gender');

        expect(search).toHaveBeenCalledTimes(1);
    });

    it('reloads a dictionary after its cache is cleared', async () => {
        const search = jest.fn().mockResolvedValue([]);
        const repositoryFactory = {
            create: jest.fn().mockReturnValue({ search }),
        } as unknown as RepositoryFactory;
        const service = new DataDictionaryService(repositoryFactory);

        await service.getItems('core.gender');
        service.clear('core.gender');
        await service.getItems('core.gender');

        expect(search).toHaveBeenCalledTimes(2);
    });
});
