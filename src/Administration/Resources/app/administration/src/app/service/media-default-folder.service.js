const { Criteria } = Contena.Data;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function createMediaDefaultFolderService() {
    const repository = Contena.Service('repositoryFactory').create('media_default_folder');

    return {
        getDefaultFolderId: (entityName) => {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', entityName));

            return repository
                .search(criteria, {
                    cacheKey: [
                        'media-default-folder',
                        entityName,
                    ],
                })
                .then((data) => {
                    return data.first().folder.id;
                })
                .catch(() => {
                    return null;
                });
        },
    };
}
