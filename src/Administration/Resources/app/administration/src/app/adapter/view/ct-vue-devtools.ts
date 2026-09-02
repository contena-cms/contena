/* istanbul ignore file */

/* Vue devtools plugins couldn't be tested well yet */
import { setupDevtoolsPlugin } from '@vue/devtools-api';
import type { App } from '@vue/devtools-api/lib/esm/api/app';
import type { DevtoolsPluginApi } from '@vue/devtools-api/lib/esm/api/api';

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export interface DevtoolComponent {
    $el?: HTMLElement & {
        DEVTOOL_EVENT_LISTENER?: () => void;
    };
    $options?: {
        extensionApiDevtoolInformation?: {
            property: string;
            method: string;
            positionId: (currentComponent: DevtoolComponent) => string;
            view: (currentComponent: DevtoolComponent) => string;
            entity: (currentComponent: DevtoolComponent) => string;
        };
    };
}

// variables which store general values
let highlightedElements: HTMLElement[] = [];

const POSITION_INSPECTOR_ID = 'ct-admin-extension-position-inspector';
const HIGHLIGHT_CLASS = 'ct-devtool-element-highlight';
const CLICKABLE_CLASS = 'ct-devtool-element-clickable';

/**
 * @private
 */
export default function setupContenaDevtools(app: App): void {
    setupDevtoolsPlugin(
        {
            // Options
            id: 'ct-admin-extension-plugin',
            label: 'Contena Admin extensions plugin',
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            app,
        },
        (api) => {
            // Add CSS for highlighting elements
            const highlightStyle = document.createElement('style');
            highlightStyle.innerHTML = `
            .${HIGHLIGHT_CLASS} {
                position: relative;
            }

            /* This allows the highlight to be displayed for empty ct-app-actions */
            .${HIGHLIGHT_CLASS}.ct-app-actions {
                width: 40px;
                height: 40px;
            }

            .${HIGHLIGHT_CLASS}::before {
              content: '';
              background-color: rgba(65, 184, 131, 0.35);
              width: 100%;
              height: 100%;
              min-height: 5px;
              position: absolute;
              z-index: 99999;
            }

            .${CLICKABLE_CLASS} {
                cursor: pointer;
            }
        `;
            document.head.appendChild(highlightStyle);

            // Add new inspector for finding the extension positions
            api.addInspector({
                id: POSITION_INSPECTOR_ID,
                label: 'Contena Extension API',
                icon: 'picture_in_picture_alt',
                actions: [
                    {
                        icon: 'flash_off',
                        tooltip: 'Unhighlight all extension positions',
                        action: (): void => {
                            unhighlightElements();
                        },
                    },
                    {
                        icon: 'flash_on',
                        tooltip: 'Highlight all extension positions',
                        action: (): void => {
                            unhighlightElements();

                            window._ct_extension_component_collection.forEach((component) => {
                                makeElementClickable(component, api);
                            });
                        },
                    },
                ],
            });

            // Load all positions into the inspector tree
            api.on.getInspectorTree((payload) => {
                if (payload.inspectorId !== POSITION_INSPECTOR_ID) {
                    return;
                }

                payload.rootNodes = [];

                window._ct_extension_component_collection.forEach((component) => {
                    const { property, positionId, view, entity } = getExtensionInformation(component);

                    // create new root node if none exists
                    const hasMatchingNode = payload.rootNodes.some((n) => n.id === property);
                    if (!hasMatchingNode) {
                        payload.rootNodes.push({
                            id: property,
                            label: property,
                            children: [],
                        });
                    }

                    const rootNode = payload.rootNodes.find((n) => n.id === property);

                    // @ts-expect-error
                    rootNode.children?.push({
                        id: `${property}_${positionId}`,
                        label: positionId === 'unknown' ? `${entity}-${view}` : positionId,
                    });
                });
            });

            // Update the state of the inspector depending on the selected node
            api.on.getInspectorState((payload) => {
                unhighlightElements();

                if (payload.inspectorId !== POSITION_INSPECTOR_ID) {
                    return;
                }

                const matchingComponent = window._ct_extension_component_collection.find((component) => {
                    const { nodeId } = getExtensionInformation(component);

                    return nodeId === payload.nodeId;
                });

                if (!matchingComponent) {
                    return;
                }

                const devtoolInformation = matchingComponent?.$options?.extensionApiDevtoolInformation;

                // show information about selected node
                payload.state = {
                    General: [],
                };

                if (devtoolInformation?.positionId?.(matchingComponent)) {
                    payload.state.General.push({
                        key: 'PositionId',
                        value: devtoolInformation.positionId(matchingComponent),
                    });
                }

                if (devtoolInformation?.view?.(matchingComponent)) {
                    payload.state.General.push({
                        key: 'View',
                        value: devtoolInformation.view(matchingComponent),
                    });
                }

                if (devtoolInformation?.entity?.(matchingComponent)) {
                    payload.state.General.push({
                        key: 'Entity',
                        value: devtoolInformation.entity(matchingComponent),
                    });
                }

                if (devtoolInformation?.property) {
                    payload.state.General.push({
                        key: 'Property',
                        value: devtoolInformation.property,
                    });
                }

                if (devtoolInformation?.method) {
                    payload.state.General.push({
                        key: 'Method',
                        value: devtoolInformation?.method,
                    });
                }

                // highlight the component in browser window
                highlightElement(matchingComponent);
            });
        },
    );
}

function highlightElement(component: DevtoolComponent): void {
    // Highlight new element
    if (component.$el) {
        component.$el.classList.add(HIGHLIGHT_CLASS);
        highlightedElements.push(component.$el);
    } else {
        // eslint-disable-next-line no-console
        console.log('Could not highlight element', component);
    }
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function makeElementClickable(component: DevtoolComponent, api: DevtoolsPluginApi<any>): void {
    highlightElement(component);

    if (component.$el) {
        component.$el?.classList.add(CLICKABLE_CLASS);

        const onElementClick = (): void => {
            const { nodeId } = getExtensionInformation(component);

            api.selectInspectorNode(POSITION_INSPECTOR_ID, nodeId);

            component.$el?.removeEventListener('click', onElementClick);
        };

        component.$el.DEVTOOL_EVENT_LISTENER = onElementClick;

        component.$el.addEventListener('click', onElementClick);
    }
}

function unhighlightElements(): void {
    highlightedElements.forEach((highlightedElement) => {
        highlightedElement.classList.remove(HIGHLIGHT_CLASS);
        highlightedElement.classList.remove(CLICKABLE_CLASS);

        // @ts-expect-error
        if (highlightedElement.DEVTOOL_EVENT_LISTENER) {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            highlightedElement.removeEventListener('click', highlightedElement.DEVTOOL_EVENT_LISTENER);
        }
    });

    highlightedElements = [];
}

function getExtensionInformation(component: DevtoolComponent): {
    nodeId: string;
    positionId: string;
    property: string;
    method: string;
    view: string;
    entity: string;
} {
    const devtoolInformation = component.$options?.extensionApiDevtoolInformation;
    const property = (devtoolInformation?.property as string) ?? 'unknown';
    const method = (devtoolInformation?.method as string) ?? 'unknown';
    const positionId = (devtoolInformation?.positionId?.(component) as string) ?? 'unknown';
    const view = (devtoolInformation?.view?.(component) as string) ?? 'unknown';
    const entity = (devtoolInformation?.entity?.(component) as string) ?? 'unknown';

    return {
        nodeId: `${property}_${positionId}`,
        positionId,
        property,
        method,
        view,
        entity,
    };
}
