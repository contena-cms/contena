import Entity, { assignSetterMethod } from '@contena/meteor-admin-sdk/es/_internals/data/Entity';

assignSetterMethod((draft, property, value) => {
    // @ts-expect-error
    Contena.Application.view.setReactive(draft as Vue, property, value);
});

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default Entity;
