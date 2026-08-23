/**
 *
 * Overview
 * This custom ESLint rule enforces that all Contena.Component.register and Component.register calls
 * pass an anonymous arrow function as the second argument. This ensures components are registered
 * with dynamic imports for lazy loading, improving performance.
 *
 * Example
 *
 * // Invalid
 * Contena.Component.register('ct-example', { template: '<div>Example</div>' });
 * Component.register('ct-example', import('./ct-example'));
 *
 * // Valid
 * Contena.Component.register('ct-example', () => import('./ct-example'));
 * Component.register('ct-example', () => import('./ct-example'));
 *
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'enforce that Contena.Component.register calls use an anonymous arrow function as the second argument',
            category: 'Possible Errors',
            recommended: true,
        },
        schema: [], // No options needed
    },
    create(context) {
        return {
            CallExpression(node) {
                // Check if the callee is Contena.Component.register or Component.register
                const isContenaRegister =
                    node.callee.type === 'MemberExpression' &&
                    node.callee.object.type === 'MemberExpression' &&
                    node.callee.object.object.name === 'Contena' &&
                    node.callee.object.property.name === 'Component' &&
                    node.callee.property.name === 'register';

                const isComponentRegister =
                    node.callee.type === 'MemberExpression' &&
                    node.callee.object.name === 'Component' &&
                    node.callee.property.name === 'register';

                if (!isContenaRegister && !isComponentRegister) {
                    return;
                }

                // Ensure there are at least 2 arguments
                if (node.arguments.length < 2) {
                    context.report({
                        node,
                        message: '{{ registerCall }} requires at least two arguments',
                        data: {
                            registerCall: isContenaRegister ? 'Contena.Component.register' : 'Component.register',
                        },
                    });
                    return;
                }

                const secondArg = node.arguments[1];

                // Check if the second argument is an arrow function
                if (secondArg.type === 'ArrowFunctionExpression') {
                    return;
                }

                context.report({
                    node: secondArg,
                    message: 'Second argument to {{ registerCall }} must be a dynamic import: {{ registerCall }}(\'ct-example\', () => import(\'./ct-example\'))',
                    data: {
                        registerCall: isContenaRegister ? 'Contena.Component.register' : 'Component.register',
                    },
                });
            },
        };
    },
};
