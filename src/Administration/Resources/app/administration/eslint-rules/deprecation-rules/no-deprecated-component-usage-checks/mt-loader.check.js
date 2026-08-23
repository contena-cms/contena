/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtLoader = (context, node) => {
    const mtLoaderComponentName = 'mt-loader';

    // Refactor the old usage of ct-loader-field to mt-loader after the migration to the new component
    if (node.name !== mtLoaderComponentName) {
        return;
    }

    /**
     * The Meteor component has identical functionality to the ct-loader component and
     * therefore no migration is needed.
     **/
}

const mtLoaderValidChecks = [
    {
        name: '"ct-loader" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <ct-loader></ct-loader>
            </template>
        `,
    }
]
const mtLoaderInvalidChecks = [
    /**
     * The Meteor component has identical functionality to the ct-loader component and
     * therefore no migration is needed.
     **/
];

module.exports = {
    mtLoaderValidChecks,
    mtLoaderInvalidChecks,
    handleMtLoader
};
