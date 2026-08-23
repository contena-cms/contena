// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default () => {
    const context = import.meta.glob('./**/!(*.spec).{j,t}s', {
        eager: true,
    });

    return Object.values(context).map((module) => module.default);
};
