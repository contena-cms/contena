import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ContenaComponent from './component';
import { Contena } from './contena';

class LifecycleTestComponent extends ContenaComponent {
    public static initCount = 0;

    public static destroyCount = 0;

    init(): void {
        LifecycleTestComponent.initCount += 1;
    }

    destroy(): void {
        LifecycleTestComponent.destroyCount += 1;
    }

    ping(value: string): string {
        return value;
    }
}

describe('Contena runtime component lifecycle', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    beforeEach(() => {
        document.body.innerHTML = '';
        LifecycleTestComponent.initCount = 0;
        LifecycleTestComponent.destroyCount = 0;

        const mutableContena = Contena as unknown as {
            componentRegistry: Map<string, typeof ContenaComponent | null>;
            instanceRegistry: unknown[];
            interceptionRegistry: Map<string, unknown[]>;
            instanceIndexByElement: WeakMap<Node, Map<string, ContenaComponent>>;
        };

        mutableContena.componentRegistry = new Map();
        mutableContena.instanceRegistry = [];
        mutableContena.interceptionRegistry = new Map();
        mutableContena.instanceIndexByElement = new WeakMap();
    });

    it('prevents duplicate initialization on the same element', () => {
        const componentName = 'CT:Lifecycle:Duplicate';
        const element = document.createElement('div');

        const first = Contena.initializeComponentOnElement(componentName, LifecycleTestComponent, element);
        const second = Contena.initializeComponentOnElement(componentName, LifecycleTestComponent, element);

        expect(first).toBe(second);
        expect(LifecycleTestComponent.initCount).toBe(1);
        expect(Contena.getComponentInstances(componentName)).toHaveLength(1);
    });

    it('initializes and destroys nested components recursively', async () => {
        const componentName = 'CT:Lifecycle:Nested';
        const root = document.createElement('div');
        root.setAttribute('data-component', componentName);
        const child = document.createElement('div');
        child.setAttribute('data-component', componentName);
        root.appendChild(child);

        const host = document.createElement('div');
        host.appendChild(root);

        const mutableContena = Contena as unknown as {
            componentRegistry: Map<string, typeof ContenaComponent>;
            handleAddedNodes(nodes: NodeList): Promise<void>;
            handleRemovedNodes(nodes: NodeList): void;
        };

        mutableContena.componentRegistry.set(componentName, LifecycleTestComponent);
        await mutableContena.handleAddedNodes(host.childNodes);

        expect(Contena.getComponentInstances(componentName)).toHaveLength(2);

        mutableContena.handleRemovedNodes(host.childNodes);
        expect(LifecycleTestComponent.destroyCount).toBe(2);
        expect(Contena.getComponentInstances(componentName)).toHaveLength(0);
    });

    it('destroys all component instances attached to the same removed node', () => {
        const node = document.createElement('div');
        Contena.initializeComponentOnElement('CT:Lifecycle:One', LifecycleTestComponent, node);
        Contena.initializeComponentOnElement('CT:Lifecycle:Two', LifecycleTestComponent, node);

        const host = document.createElement('div');
        host.appendChild(node);

        const mutableContena = Contena as unknown as {
            handleRemovedNodes(nodes: NodeList): void;
        };
        mutableContena.handleRemovedNodes(host.childNodes);

        expect(LifecycleTestComponent.destroyCount).toBe(2);
        expect(Contena.getComponentInstances('CT:Lifecycle:One')).toHaveLength(0);
        expect(Contena.getComponentInstances('CT:Lifecycle:Two')).toHaveLength(0);
    });

    it('clears indexed lookups after node removal', () => {
        const componentName = 'CT:Lifecycle:IndexedLookup';
        const node = document.createElement('div');
        const host = document.createElement('div');
        host.appendChild(node);

        const instance = Contena.initializeComponentOnElement(componentName, LifecycleTestComponent, node);
        expect(instance).toBeDefined();
        expect(Contena.getComponentInstanceByElement(componentName, node)).toBe(instance);

        const mutableContena = Contena as unknown as {
            handleRemovedNodes(nodes: NodeList): void;
        };
        mutableContena.handleRemovedNodes(host.childNodes);

        expect(Contena.getComponentInstanceByElement(componentName, node)).toBeUndefined();
    });

    it('returns undefined and logs errors when component import fails', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        const component = await Contena.getComponent('non-existing-component-specifier');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
    });

    it('caches failed component imports to avoid repeated retries', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        const firstResult = await Contena.getComponent('non-existing-component-specifier');
        const secondResult = await Contena.getComponent('non-existing-component-specifier');

        expect(firstResult).toBeUndefined();
        expect(secondResult).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
    });

    it('allows cross-origin component imports resolved from import maps', async () => {
        const importMapScript = document.createElement('script');
        importMapScript.type = 'importmap';
        importMapScript.textContent = JSON.stringify({
            imports: {
                'CT:FromCdn': 'https://cdn.example.com/component-from-cdn.js',
            },
        });
        document.body.appendChild(importMapScript);

        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Contena.getComponent('CT:FromCdn');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component CT:FromCdn:');
    });

    it('tries to import cross-origin component specifiers directly', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Contena.getComponent('https://evil.example/blocked-component.js');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component https://evil.example/blocked-component.js:');
    });

    it('tries to import unsafe-protocol component specifiers directly', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Contena.getComponent('javascript:alert(1)');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component javascript:alert(1):');
    });

    it('allows same-origin import-map URLs and only fails on missing module', async () => {
        const importMapScript = document.createElement('script');
        importMapScript.type = 'importmap';
        importMapScript.textContent = JSON.stringify({
            imports: {
                'CT:Local': `${window.location.origin}/does-not-exist-component.js`,
            },
        });
        document.body.appendChild(importMapScript);

        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Contena.getComponent('CT:Local');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component CT:Local:');
    });

    it('allows loopback Vite /@fs/ component URLs in dev-server mode', async () => {
        const importMapScript = document.createElement('script');
        importMapScript.type = 'importmap';
        importMapScript.textContent = JSON.stringify({
            imports: {
                'CT:DevFs': 'http://localhost:5175/@fs/var/www/html/src/Frontend/Resources/views/components/CT/Custom/Test.js',
            },
        });
        document.body.appendChild(importMapScript);

        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Contena.getComponent('CT:DevFs');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component CT:DevFs:');
    });

    it('runs interceptors in descending priority order', () => {
        Contena.intercept('runtime:interceptor', (payload) => ({ ...payload, order: 'low' }), 1);
        Contena.intercept('runtime:interceptor', (payload) => ({ ...payload, order: 'high' }), 20);

        const result = Contena.emitInterception('runtime:interceptor', { order: 'initial' });

        expect(result).toEqual({ order: 'low' });
    });

    it('emits queued events asynchronously', async () => {
        const listener = vi.fn();
        Contena.on('runtime:queued', listener);

        Contena.emitQueued('runtime:queued', 'payload');
        expect(listener).not.toHaveBeenCalled();

        await Promise.resolve();

        expect(listener).toHaveBeenCalledOnce();
        expect(listener).toHaveBeenCalledWith('payload');
    });

    it('safely ignores callMethod invocations for missing methods', () => {
        const componentName = 'CT:Lifecycle:Methods';
        const element = document.createElement('div');
        Contena.initializeComponentOnElement(componentName, LifecycleTestComponent, element);

        expect(() => Contena.callMethod(componentName, 'doesNotExist', 'value')).not.toThrow();
        expect(() => Contena.callMethod(componentName, 'ping', 'pong')).not.toThrow();
    });

    it('does not register observers and listeners on repeated construction', () => {
        const addEventListenerSpy = vi.spyOn(document, 'addEventListener');
        const observeSpy = vi.spyOn(MutationObserver.prototype, 'observe');
        const contenaConstructor = Contena.constructor as { new (): unknown };

        const first = new contenaConstructor();
        const second = new contenaConstructor();

        expect(first).toBe(Contena);
        expect(second).toBe(Contena);
        expect(addEventListenerSpy).not.toHaveBeenCalled();
        expect(observeSpy).not.toHaveBeenCalled();
    });

    it('disconnect clears observers, listeners, registries, and instances', () => {
        const observerDisconnectSpy = vi.spyOn(MutationObserver.prototype, 'disconnect');
        const removeEventListenerSpy = vi.spyOn(document, 'removeEventListener');
        const emitterListener = vi.fn();
        const node = document.createElement('div');

        Contena.on('runtime:event', emitterListener);
        Contena.intercept('runtime:interceptor', (payload) => ({ ...payload, order: 'intercepted' }), 10);
        Contena.initializeComponentOnElement('CT:Lifecycle:Disconnect', LifecycleTestComponent, node);

        Contena.disconnect();

        expect(observerDisconnectSpy).toHaveBeenCalledOnce();
        expect(removeEventListenerSpy).toHaveBeenCalledWith('DOMContentLoaded', expect.any(Function));
        expect(LifecycleTestComponent.destroyCount).toBe(1);
        expect(Contena.getComponentInstances('CT:Lifecycle:Disconnect')).toHaveLength(0);
        expect(Contena.emitInterception('runtime:interceptor', { order: 'initial' })).toEqual({ order: 'initial' });

        Contena.emit('runtime:event');
        expect(emitterListener).not.toHaveBeenCalled();
    });
});
