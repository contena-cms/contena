/* eslint-disable ct-deprecation-rules/private-feature-declarations */
/* global Entity */
import type RepositoryFactory from 'src/core/data/repository-factory.data';

export interface DataDictionaryOption {
    value: string;
    label: string;
    code: string;
}

/** Shared, extension-friendly access to dictionary values for Admin screens and plugins. */
export default class DataDictionaryService {
    private readonly repositoryFactory: RepositoryFactory;

    private readonly cache = new Map<string, Promise<Entity<'data_dictionary_item'>[]>>();

    public constructor(repositoryFactory: RepositoryFactory) {
        this.repositoryFactory = repositoryFactory;
    }

    public getItems(technicalName: string, activeOnly = true): Promise<Entity<'data_dictionary_item'>[]> {
        const cacheKey = `${technicalName}:${activeOnly ? 'active' : 'all'}`;
        const cached = this.cache.get(cacheKey);
        if (cached) {
            return cached;
        }

        const request = this.loadItems(technicalName, activeOnly);
        this.cache.set(cacheKey, request);

        return request;
    }

    public async getOptions(technicalName: string, activeOnly = true): Promise<DataDictionaryOption[]> {
        const items = await this.getItems(technicalName, activeOnly);

        return items.map((item) => ({
            value: item.code,
            code: item.code,
            label: item.label || item.code,
        }));
    }

    public clear(technicalName?: string): void {
        if (!technicalName) {
            this.cache.clear();
            return;
        }

        [...this.cache.keys()].filter((key) => key.startsWith(`${technicalName}:`)).forEach((key) => this.cache.delete(key));
    }

    private async loadItems(technicalName: string, activeOnly: boolean): Promise<Entity<'data_dictionary_item'>[]> {
        const criteria = new Contena.Data.Criteria(1, 100);
        criteria.addFilter(Contena.Data.Criteria.equals('dictionary.technicalName', technicalName));
        if (activeOnly) {
            criteria.addFilter(Contena.Data.Criteria.equals('active', true));
        }
        criteria.addSorting(Contena.Data.Criteria.sort('position', 'ASC'));
        criteria.setTotalCountMode(0);

        const repository = this.repositoryFactory.create('data_dictionary_item');
        const result = await repository.search(criteria, Contena.Context.api);

        return Array.from(result);
    }
}
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
