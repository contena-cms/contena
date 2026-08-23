import { mapState, mapActions } from 'pinia';
import * as mapErrors from 'src/app/service/map-errors.service';

const componentHelper: ComponentHelper = {
    mapState,
    mapActions,
    ...mapErrors,
};

// Register each component helper
(Object.entries(componentHelper) as [keyof ComponentHelper, ComponentHelper[keyof ComponentHelper]][]).forEach(
    ([
        name,
        value,
    ]) => {
        Contena.Component.registerComponentHelper(name, value);
    },
);

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function initializeComponentHelper() {
    return Contena.Component.getComponentHelper();
}
