// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default () => {
    // @ts-expect-error
    const context = import.meta.glob('./**/!(*.spec).{j,t}s', {
        eager: true,
        import: 'default',
    });

    return Object.values(context);
};
