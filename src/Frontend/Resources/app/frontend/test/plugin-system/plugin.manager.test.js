import PluginManager from 'src/plugin-system/plugin.manager';
import Plugin from 'src/plugin-system/plugin.class';

class FooPluginClass extends Plugin {
    init() {}
}

class AsyncPluginClass extends Plugin {
    init() {}
}

class SinglePlugin extends Plugin {
    init() {}
}

class AsyncPluginClassWithMethods extends Plugin {
    init() {}

    static sayHello() {
        return 'Hello';
    }
}

class CoreWidgetPluginClass extends Plugin {
    init() {}

    getValue() {
        return 'base';
    }
}

class OverrideWidgetPluginClass extends CoreWidgetPluginClass {
    getValue() {
        return 'override';
    }
}

const clickCounter = { count: 0 };

class ListeningCorePluginClass extends Plugin {
    init() {
        this._onClick = () => { clickCounter.count += 1; };
        this.el.addEventListener('click', this._onClick);
    }

    destroy() {
        this.el.removeEventListener('click', this._onClick);
    }
}

class ListeningOverridePluginClass extends ListeningCorePluginClass {
}

/**
 * @package frontend
 */
describe('Plugin manager', () => {
    beforeEach(() => {
        document.body.innerHTML = '<div data-plugin="true" class="test-class"></div><div id="test-id"></div>';

        jest.spyOn(console, 'error').mockImplementation();
    });

    afterEach(() => {
        jest.resetAllMocks();
        expect(console.error).not.toHaveBeenCalled();
    });

    it('should not fail for non-existing id', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, '#nonExistingId');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', '#nonExistingId');
    });

    it('should not fail for non-existing HTML tag', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, 'nonExistingHtmlTag');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', 'nonExistingHtmlTag');
    });

    it('should not fail for non-existing class', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, '.non-existing-class');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', '.non-existing-class');
    });

    it('should not fail for non-existing selector', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, '[data-non-existing-data-attribute]');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(0);

        PluginManager.deregister('FooPlugin', '[data-non-existing-data-attribute]');
    });

    it('should initialize plugin with class selector', async () => {
        PluginManager.register('FooPlugin', FooPluginClass, '.test-class');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('FooPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPlugin', '.test-class');
    });

    it('should initialize plugin with id selector', async () => {
        PluginManager.register('FooPluginID', FooPluginClass, '#test-id');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginID').length).toBe(1);
        expect(PluginManager.getPluginInstances('FooPluginID')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginID', '#test-id');
    });

    it('should initialize plugin with tag selector', async () => {
        PluginManager.register('FooPluginTag', FooPluginClass, 'div');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginTag').length).toBe(2);

        PluginManager.getPluginInstances('FooPluginTag').forEach((instance) => {
            expect(instance._initialized).toBe(true);
        });

        PluginManager.deregister('FooPluginTag', 'div');
    });

    it('should initialize plugin with data-attribute selector', async () => {
        PluginManager.register('FooPluginDataAttr', FooPluginClass, '[data-plugin]');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginDataAttr').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginDataAttr')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginDataAttr', '[data-plugin]');
    });

    it('should initialize plugin with mixed selector (class and data-attribute)', async () => {
        const selector = '.test-class[data-plugin]';
        PluginManager.register('FooPluginClassDataAttr', FooPluginClass, selector);

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginClassDataAttr').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginClassDataAttr')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginClassDataAttr', selector);
    });

    it('should initialize plugin with node selector', async () => {

        PluginManager.register('FooPluginClassOnDocument', FooPluginClass, document);

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginClassOnDocument').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginClassOnDocument')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginClassOnDocument');
    });

    it('should initialize plugin with no selector (fallback to document)', async () => {

        PluginManager.register('FooPluginClassWithoutSelector', FooPluginClass);

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('FooPluginClassWithoutSelector').length).toBe(1);

        expect(PluginManager.getPluginInstances('FooPluginClassWithoutSelector')[0]._initialized).toBe(true);

        PluginManager.deregister('FooPluginClassWithoutSelector');
    });

    it('should initialize plugin with async import', async () => {
        const asyncImport = new Promise((resolve) => {
            resolve({ default: AsyncPluginClass });
        });

        PluginManager.register('AsyncTest', () => asyncImport, '.test-class');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('AsyncTest').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncTest')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncTest', '.test-class');
    });

    it('should initialize plugin with async import on DOM element', async () => {
        const asyncImport = new Promise((resolve) => {
            resolve({ default: AsyncPluginClass });
        });

        const element = document.querySelector('.test-class');

        PluginManager.register('AsyncWithElement', () => asyncImport, element);

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('AsyncWithElement').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncWithElement')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncWithElement', element);
    });

    it('should initialize multiple plugins with async import', async () => {
        const asyncImport1 = new Promise((resolve) => {
            resolve({ default: AsyncPluginClass });
        });

        const asyncImport2 = new Promise((resolve) => {
            resolve({ default: AsyncPluginClassWithMethods });
        });

        PluginManager.register('Async1', () => asyncImport1, '.test-class');
        PluginManager.register('Async2', () => asyncImport2, '#test-id');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('Async1').length).toBe(1);
        expect(PluginManager.getPluginInstances('Async1')[0]._initialized).toBe(true);

        expect(PluginManager.getPluginInstances('Async2').length).toBe(1);
        expect(PluginManager.getPluginInstances('Async2')[0]._initialized).toBe(true);

        PluginManager.deregister('Async1', '.test-class');
        PluginManager.deregister('Async2', '#test-id');
    });

    it('should continue initializing other plugins when one async import fails', async () => {
        const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();

        const failingAsyncImport = new Promise((resolve, reject) => {
            reject(new Error('Chunk not found'));
        });

        const successfulAsyncImport = new Promise((resolve) => {
            resolve({ default: AsyncPluginClass });
        });

        PluginManager.register('AsyncFailing', () => failingAsyncImport, '.test-class');
        PluginManager.register('AsyncSuccess', () => successfulAsyncImport, '#test-id');
        PluginManager.register('SyncSuccess', FooPluginClass, '[data-plugin]');

        await PluginManager.initializePlugins();

        expect(PluginManager.getPluginInstances('AsyncFailing').length).toBe(0);
        expect(PluginManager.getPluginInstances('AsyncSuccess').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncSuccess')[0]._initialized).toBe(true);
        expect(PluginManager.getPluginInstances('SyncSuccess').length).toBe(1);
        expect(PluginManager.getPluginInstances('SyncSuccess')[0]._initialized).toBe(true);

        expect(consoleWarnSpy).toHaveBeenCalledWith(
            'The async plugin "AsyncFailing" could not be loaded and will be skipped.',
            expect.any(Error),
        );

        PluginManager.deregister('AsyncFailing', '.test-class');
        PluginManager.deregister('AsyncSuccess', '#test-id');
        PluginManager.deregister('SyncSuccess', '[data-plugin]');
    });

    it('should initialize plugins in correct order, regardless if they are async', async () => {
        document.body.innerHTML = `
            <div data-async-one="true"></div>
            <div data-async-two="true"></div>
            <div data-sync-plugin="true"></div>
        `;

        const spyInit1 = jest.spyOn(AsyncPluginClass.prototype, 'init');
        const spyInit2 = jest.spyOn(FooPluginClass.prototype, 'init');
        const spyInit3 = jest.spyOn(AsyncPluginClassWithMethods.prototype, 'init');

        const asyncImport1 = new Promise((resolve) => {
            resolve({ default: AsyncPluginClass });
        });

        const asyncImport2 = new Promise((resolve) => {
            resolve({ default: AsyncPluginClassWithMethods });
        });

        PluginManager.register('Plugin1', () => asyncImport1, '[data-async-one]');
        PluginManager.register('Plugin2', FooPluginClass, '[data-sync-plugin]');
        PluginManager.register('Plugin3', () => asyncImport2, '[data-async-two]');

        await PluginManager.initializePlugins();

        // Ensure all init methods are called
        expect(spyInit1).toHaveBeenCalledTimes(1);
        expect(spyInit2).toHaveBeenCalledTimes(1);
        expect(spyInit3).toHaveBeenCalledTimes(1);

        // Ensure plugins are initialized in correct order
        expect(spyInit1.mock.invocationCallOrder[0]).toBeLessThan(spyInit2.mock.invocationCallOrder[0]);
        expect(spyInit2.mock.invocationCallOrder[0]).toBeLessThan(spyInit3.mock.invocationCallOrder[0]);

        PluginManager.deregister('Plugin1', '[data-async-one]');
        PluginManager.deregister('Plugin2', '[data-sync-plugin]');
        PluginManager.deregister('Plugin3', '[data-async-two]');
    });

    it('should be able get plugin instance from element', async () => {
        document.body.innerHTML = `
            <div data-core-widget="true"></div>
        `;

        PluginManager.register('CoreWidget', CoreWidgetPluginClass, '[data-core-widget]');
        await PluginManager.initializePlugins();

        const element = document.querySelector('[data-core-widget]');
        const coreWidgetPluginInstance = PluginManager.getPluginInstanceFromElement(element, 'CoreWidget');

        expect(PluginManager.getPluginInstances('CoreWidget').length).toBe(1);
        expect(coreWidgetPluginInstance.getValue()).toBe('base');

        PluginManager.deregister('CoreWidget', '[data-core-widget]');
    });

    it('should be able to override sync plugin', async () => {
        document.body.innerHTML = `
            <div data-cart="true"></div>
        `;

        // Contena core registers plugin
        PluginManager.register('CoreWidget', CoreWidgetPluginClass, '[data-cart]');

        // App/plugin attempts to override core plugin
        PluginManager.override('CoreWidget', OverrideWidgetPluginClass, '[data-cart]');

        await PluginManager.initializePlugins();

        const element = document.querySelector('[data-cart]');
        const widgetPluginInstance = PluginManager.getPluginInstanceFromElement(element, 'CoreWidget');

        expect(PluginManager.getPluginInstances('CoreWidget').length).toBe(1);
        expect(widgetPluginInstance.getValue()).toBe('override');

        PluginManager.deregister('CoreWidget', '[data-cart]');
    });

    it('should initialize single sync plugin on string selector', async () => {
        document.body.innerHTML = `
            <div data-single="true"></div>
        `;

        PluginManager.register('SinglePlugin', SinglePlugin, '[data-single]');

        await PluginManager.initializePlugin('SinglePlugin', '[data-single]', {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('SinglePlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('SinglePlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('SinglePlugin', '[data-single]');
    });

    it('should initialize single sync plugin on DOM node', async () => {
        document.body.innerHTML = `
            <div data-single="true"></div>
        `;
        const element = document.querySelector('[data-single]');

        PluginManager.register('SingleDomPlugin', SinglePlugin, element);

        await PluginManager.initializePlugin('SingleDomPlugin', element, {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('SingleDomPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('SingleDomPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('SingleDomPlugin', element);
    });

    it('should initialize single async plugin on string selector', async () => {
        document.body.innerHTML = `
            <div data-async-single="true"></div>
        `;

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        PluginManager.register('AsyncSinglePlugin', () => asyncImport, '[data-async-single]');

        await PluginManager.initializePlugin('AsyncSinglePlugin', '[data-async-single]', {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('AsyncSinglePlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncSinglePlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncSinglePlugin', '[data-async-single]');
    });

    it('should initialize single async plugin on DOM node', async () => {
        document.body.innerHTML = `
            <div data-async-single="true"></div>
        `;

        const element = document.querySelector('[data-async-single]');

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        PluginManager.register('AsyncSingleDomPlugin', () => asyncImport, element);

        await PluginManager.initializePlugin('AsyncSingleDomPlugin', element, {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('AsyncSingleDomPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncSingleDomPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncSingleDomPlugin', element);
    });

    it('should skip single async plugin when async import fails', async () => {
        document.body.innerHTML = `
            <div data-async-single="true"></div>
        `;

        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        const asyncImport = new Promise((resolve, reject) => {
            reject(new Error('Chunk not found'));
        });

        PluginManager.register('AsyncSingleFailingPlugin', () => asyncImport, '[data-async-single]');

        await PluginManager.initializePlugin('AsyncSingleFailingPlugin', '[data-async-single]', {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('AsyncSingleFailingPlugin').length).toBe(0);
        expect(consoleSpy).toHaveBeenCalledWith(
            'The async plugin "AsyncSingleFailingPlugin" could not be loaded and will be skipped.',
            expect.any(Error),
        );

        PluginManager.deregister('AsyncSingleFailingPlugin', '[data-async-single]');
    });

    it('should not initialize single async plugin when selector is not found in the DOM', async () => {
        document.body.innerHTML = `
            <div class="i-am-not-the-plugin-selector"></div>
        `;

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        PluginManager.register('AsyncPluginWithoutFoundSelector', () => asyncImport, '[data-async-single]');

        await PluginManager.initializePlugin('AsyncPluginWithoutFoundSelector', '[data-async-single]', {});

        await new Promise(process.nextTick);

        // No instance is found because the selector is not in the DOM
        expect(PluginManager.getPluginInstances('AsyncPluginWithoutFoundSelector').length).toBe(0);

        PluginManager.deregister('AsyncPluginWithoutFoundSelector', '[data-async-single]');
    });

    it('should initialize single async plugin on selector that differs from original register selector', async () => {
        document.body.innerHTML = `
            <div class="different-selector"></div>
        `;

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        // Plugin is registered with selector '[data-async-single]'
        PluginManager.register('AsyncDifferentSelectorPlugin', () => asyncImport, '[data-async-single]');

        // Plugin is then initialized with selector '.different-selector'
        await PluginManager.initializePlugin('AsyncDifferentSelectorPlugin', '.different-selector', {});

        await new Promise(process.nextTick);

        expect(PluginManager.getPluginInstances('AsyncDifferentSelectorPlugin').length).toBe(1);
        expect(PluginManager.getPluginInstances('AsyncDifferentSelectorPlugin')[0]._initialized).toBe(true);

        PluginManager.deregister('AsyncDifferentSelectorPlugin', '[data-async-single]');
    });

    it('should be able to modify the options when initializing a single async plugin', async () => {
        document.body.innerHTML = `
            <div data-async-single="true"></div>
        `;

        const asyncImport = new Promise((resolve) => {
            resolve({ default: SinglePlugin });
        });

        PluginManager.register('AsyncPluginWithOpts', () => asyncImport, '[data-async-single]', {
            displayText: 'The initial display text',
        });

        await PluginManager.initializePlugin('AsyncPluginWithOpts', '[data-async-single]', {
            displayText: 'A different display text',
            newOption: 'A new option',
        });

        await new Promise(process.nextTick);

        // Verify that the options were correctly set
        expect(PluginManager.getPluginInstances('AsyncPluginWithOpts')[0].options).toEqual({
            displayText: 'A different display text',
            newOption: 'A new option',
        });

        PluginManager.deregister('AsyncPluginWithOpts', '[data-async-single]');
    });

    it('should show console error when plugin initialization fails', async () => {
        document.body.innerHTML = `
            <div data-async-single-with-error="true"></div>
        `;

        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        // Cause some trouble by returning a non-class
        const asyncImport = new Promise((resolve) => {
            resolve({ default: 'NOT_A_CLASS' });
        });

        PluginManager.register('AsyncErrorPlugin', () => asyncImport, '[data-async-single-with-error]', {});

        await PluginManager.initializePlugin('AsyncErrorPlugin', '[data-async-single-with-error]', {});

        await new Promise(process.nextTick);

        expect(consoleSpy).toHaveBeenCalledWith('The passed plugin is not a function or a class.');

        expect(PluginManager.getPluginInstances('AsyncErrorPlugin').length).toBe(0);

        PluginManager.deregister('AsyncErrorPlugin', '[data-async-single-with-error]');
    });

    it('should be able to override async plugin', async () => {
        document.body.innerHTML = `
            <div data-async-cart="true"></div>
        `;

        const asyncCoreWidgetImport = new Promise((resolve) => {
            resolve({ default: CoreWidgetPluginClass });
        });

        const asyncOverrideWidgetImport = new Promise((resolve) => {
            resolve({ default: OverrideWidgetPluginClass });
        });

        // Contena core registers async plugin
        PluginManager.register('AsyncCoreWidget', () => asyncCoreWidgetImport, '[data-async-cart]');

        // App/plugin attempts to override async core plugin
        PluginManager.override('AsyncCoreWidget', () => asyncOverrideWidgetImport, '[data-async-cart]');

        PluginManager.initializePlugins();

        await new Promise(process.nextTick);

        const element = document.querySelector('[data-async-cart]');
        const widgetPluginInstance = PluginManager.getPluginInstanceFromElement(element, 'AsyncCoreWidget');

        expect(PluginManager.getPluginInstances('AsyncCoreWidget').length).toBe(1);
        expect(widgetPluginInstance.getValue()).toBe('override');

        PluginManager.deregister('AsyncCoreWidget', '[data-async-cart]');
    });

    describe('override of async plugins', () => {
        const coreImport = () => Promise.resolve({ default: CoreWidgetPluginClass });
        const overrideImport = () => Promise.resolve({ default: OverrideWidgetPluginClass });

        beforeEach(() => {
            document.body.innerHTML = '<div data-overridable-widget="true"></div>';
        });

        afterEach(() => {
            // Deregister WITHOUT a selector. With a selector, PluginRegistry.delete() removes only
            // the registration and keeps the plugin map, including the `instances` array, so
            // instances would accumulate across the tests in this block.
            PluginManager.deregister('OverridableWidget');
        });

        it('should not let a late async resolution overwrite a newer override', async () => {
            // Control when the core chunk resolves, so the override deterministically lands inside
            // the _fetchAsyncPlugins() await window instead of relying on microtask counts.
            let resolveCoreImport;
            const controlledCoreImport = () => new Promise((resolve) => {
                resolveCoreImport = () => resolve({ default: CoreWidgetPluginClass });
            });

            PluginManager.register('OverridableWidget', controlledCoreImport, '[data-overridable-widget]');

            const initPromise = PluginManager.initializePlugins();
            await new Promise(process.nextTick);

            // Theme code that registers late, e.g. from its own DOMContentLoaded listener.
            PluginManager.override('OverridableWidget', overrideImport, '[data-overridable-widget]');

            resolveCoreImport();
            await initPromise;
            await new Promise(process.nextTick);

            const element = document.querySelector('[data-overridable-widget]');

            expect(PluginManager.getPlugin('OverridableWidget').get('class')).toBe(OverrideWidgetPluginClass);
            expect(PluginManager.getPluginInstanceFromElement(element, 'OverridableWidget').getValue()).toBe('override');
        });

        it('should replace an already initialized instance when the plugin is overridden afterwards', async () => {
            PluginManager.register('OverridableWidget', coreImport, '[data-overridable-widget]');
            await PluginManager.initializePlugins();

            PluginManager.override('OverridableWidget', overrideImport, '[data-overridable-widget]');
            await PluginManager.initializePlugins();
            await new Promise(process.nextTick);

            const element = document.querySelector('[data-overridable-widget]');

            expect(PluginManager.getPluginInstanceFromElement(element, 'OverridableWidget').getValue()).toBe('override');
        });

        it('should not keep outdated instances in the plugin instance list after a replacement', async () => {
            PluginManager.register('OverridableWidget', coreImport, '[data-overridable-widget]');
            await PluginManager.initializePlugins();

            PluginManager.override('OverridableWidget', overrideImport, '[data-overridable-widget]');
            await PluginManager.initializePlugins();
            await new Promise(process.nextTick);

            const instances = PluginManager.getPluginInstances('OverridableWidget');

            expect(instances.length).toBe(1);
            expect(instances[0]).toBeInstanceOf(OverrideWidgetPluginClass);
        });

        it('should be able to override an async plugin with a sync class', async () => {
            PluginManager.register('OverridableWidget', coreImport, '[data-overridable-widget]');
            PluginManager.override('OverridableWidget', OverrideWidgetPluginClass, '[data-overridable-widget]');

            await PluginManager.initializePlugins();
            await new Promise(process.nextTick);

            const element = document.querySelector('[data-overridable-widget]');

            expect(PluginManager.getPlugin('OverridableWidget').get('async')).toBe(false);
            expect(PluginManager.getPluginInstanceFromElement(element, 'OverridableWidget').getValue()).toBe('override');
        });

        it('should keep the override when the plugin is initialized on a single element', async () => {
            PluginManager.register('OverridableWidget', coreImport, '[data-overridable-widget]');
            await PluginManager.initializePlugins();

            const element = document.querySelector('[data-overridable-widget]');

            PluginManager.override('OverridableWidget', overrideImport, '[data-overridable-widget]');
            await PluginManager.initializePlugin('OverridableWidget', element);
            await new Promise(process.nextTick);

            expect(PluginManager.getPluginInstanceFromElement(element, 'OverridableWidget').getValue()).toBe('override');
        });

        it('should re-initialize existing instances when the override is registered late', async () => {
            PluginManager.register('OverridableWidget', coreImport, '[data-overridable-widget]');
            await PluginManager.initializePlugins();

            PluginManager.override('OverridableWidget', overrideImport, '[data-overridable-widget]');

            // No further initializePlugins() call, the override has to repair the page itself.
            await new Promise(process.nextTick);

            const element = document.querySelector('[data-overridable-widget]');

            expect(PluginManager.getPluginInstanceFromElement(element, 'OverridableWidget').getValue()).toBe('override');
        });

        it('should not leave the listeners of a replaced instance attached', async () => {
            document.body.innerHTML = '<div data-listening-widget="true"></div>';
            clickCounter.count = 0;

            PluginManager.register('ListeningWidget', () => Promise.resolve({ default: ListeningCorePluginClass }), '[data-listening-widget]');
            await PluginManager.initializePlugins();

            PluginManager.override('ListeningWidget', () => Promise.resolve({ default: ListeningOverridePluginClass }), '[data-listening-widget]');
            await new Promise(process.nextTick);

            const element = document.querySelector('[data-listening-widget]');

            expect(PluginManager.getPluginInstanceFromElement(element, 'ListeningWidget')).toBeInstanceOf(ListeningOverridePluginClass);

            element.dispatchEvent(new Event('click'));

            // The replaced instance must not handle the event a second time.
            expect(clickCounter.count).toBe(1);

            PluginManager.deregister('ListeningWidget');
        });

        it('should warn when a replaced instance does not implement destroy', async () => {
            const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

            PluginManager.register('OverridableWidget', coreImport, '[data-overridable-widget]');
            await PluginManager.initializePlugins();

            PluginManager.override('OverridableWidget', overrideImport, '[data-overridable-widget]');
            await new Promise(process.nextTick);

            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('does not implement destroy()'));
        });

        it('should not destroy an instance that is already an instance of the registered class', async () => {
            PluginManager.register('OverridableWidget', overrideImport, '[data-overridable-widget]');
            await PluginManager.initializePlugins();

            const element = document.querySelector('[data-overridable-widget]');
            const firstInstance = PluginManager.getPluginInstanceFromElement(element, 'OverridableWidget');

            await PluginManager.initializePlugins();

            expect(PluginManager.getPluginInstanceFromElement(element, 'OverridableWidget')).toBe(firstInstance);
            expect(PluginManager.getPluginInstances('OverridableWidget').length).toBe(1);
        });

        it('should be able to extend an async plugin under a new name', async () => {
            PluginManager.register('OverridableWidget', coreImport, '[data-overridable-widget]');

            PluginManager.extend('OverridableWidget', 'ExtendedCart', {
                getValue() {
                    return 'extended';
                },
            }, '[data-overridable-widget]');

            await PluginManager.initializePlugins();
            await new Promise(process.nextTick);

            const element = document.querySelector('[data-overridable-widget]');

            expect(PluginManager.getPluginInstanceFromElement(element, 'ExtendedCart').getValue()).toBe('extended');

            PluginManager.deregister('ExtendedCart');
        });

        it('should apply the override when the plugin is initialized within a parent element', async () => {
            // The path taken for AJAX loaded content, e.g. an offcanvas or a reloaded listing.
            document.body.innerHTML = '<div id="ajax-content"><div data-overridable-widget="true"></div></div>';

            PluginManager.register('OverridableWidget', coreImport, '[data-overridable-widget]');
            await PluginManager.initializePlugins();

            PluginManager.override('OverridableWidget', overrideImport, '[data-overridable-widget]');
            await new Promise(process.nextTick);

            await PluginManager.initializePluginsInParentElement(document.getElementById('ajax-content'));
            await new Promise(process.nextTick);

            const element = document.querySelector('[data-overridable-widget]');

            expect(PluginManager.getPluginInstanceFromElement(element, 'OverridableWidget').getValue()).toBe('override');
            expect(PluginManager.getPluginInstances('OverridableWidget').length).toBe(1);
        });

        it('should reset the emitter even when destroy throws', async () => {
            document.body.innerHTML = '<div data-throwing-widget="true"></div>';

            class ThrowingCorePluginClass extends Plugin {
                init() {}

                destroy() {
                    throw new Error('destroy failed');
                }
            }

            class ThrowingOverridePluginClass extends ThrowingCorePluginClass {
            }

            PluginManager.register('ThrowingWidget', () => Promise.resolve({ default: ThrowingCorePluginClass }), '[data-throwing-widget]');
            await PluginManager.initializePlugins();

            const element = document.querySelector('[data-throwing-widget]');
            const outdatedInstance = PluginManager.getPluginInstanceFromElement(element, 'ThrowingWidget');
            const resetSpy = jest.spyOn(outdatedInstance.$emitter, 'reset');

            jest.spyOn(console, 'warn').mockImplementation();

            PluginManager.override('ThrowingWidget', () => Promise.resolve({ default: ThrowingOverridePluginClass }), '[data-throwing-widget]');
            await new Promise(process.nextTick);

            // A throwing destroy() must not prevent the emitter from being reset.
            expect(resetSpy).toHaveBeenCalled();
            expect(PluginManager.getPluginInstanceFromElement(element, 'ThrowingWidget')).toBeInstanceOf(ThrowingOverridePluginClass);

            PluginManager.deregister('ThrowingWidget');
        });

        it('should resolve a deferred extended plugin to the same class on every import', async () => {
            PluginManager.register('OverridableWidget', coreImport, '[data-overridable-widget]');

            PluginManager.extend('OverridableWidget', 'MemoizedWidget', {
                getValue() {
                    return 'memoized';
                },
            }, '[data-overridable-widget]');

            const deferredImport = PluginManager.getPlugin('MemoizedWidget').get('class');

            const [first, second] = await Promise.all([deferredImport(), deferredImport()]);

            expect(first.default).toBe(second.default);

            PluginManager.deregister('MemoizedWidget');
        });

        it('should warn when a plugin keeps being re-registered while it loads', async () => {
            const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

            // Re-register with a *different* import function on every resolution, so the
            // compare-and-swap can never win and the retry budget is exhausted.
            const createLosingImport = () => () => Promise.resolve().then(() => {
                PluginManager.deregister('OverridableWidget', '[data-overridable-widget]');
                PluginManager.register('OverridableWidget', createLosingImport(), '[data-overridable-widget]');

                return { default: CoreWidgetPluginClass };
            });

            PluginManager.register('OverridableWidget', createLosingImport(), '[data-overridable-widget]');

            await PluginManager.initializePlugins();
            await new Promise(process.nextTick);

            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('more often than the plugin manager retries'));

            const element = document.querySelector('[data-overridable-widget]');

            expect(PluginManager.getPluginInstanceFromElement(element, 'OverridableWidget')).toBeUndefined();
        });

        it('should be able to extend a sync plugin under a new name', async () => {
            PluginManager.register('OverridableWidget', CoreWidgetPluginClass, '[data-overridable-widget]');

            PluginManager.extend('OverridableWidget', 'ExtendedSyncCart', {
                getValue() {
                    return '2,00 EUR';
                },
            }, '[data-overridable-widget]');

            await PluginManager.initializePlugins();

            const element = document.querySelector('[data-overridable-widget]');

            expect(PluginManager.getPluginInstanceFromElement(element, 'ExtendedSyncCart').getValue()).toBe('2,00 EUR');

            PluginManager.deregister('ExtendedSyncCart');
        });
    });

    it('should warn when registering already registered plugin', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        PluginManager.register('DuplicatePlugin', FooPluginClass, '.test-class');
        PluginManager.register('DuplicatePlugin', FooPluginClass, '.test-class');

        expect(consoleSpy).toHaveBeenCalledWith('Plugin "DuplicatePlugin" is already registered.');

        PluginManager.deregister('DuplicatePlugin', '.test-class');
        jest.resetAllMocks();
    });

    it('should warn when deregistering non-registered plugin', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        PluginManager.deregister('NonExistentPlugin', '.test-class');

        expect(consoleSpy).toHaveBeenCalledWith('The plugin "NonExistentPlugin" is not registered.');
        jest.resetAllMocks();
    });

    it('should warn when extending non-registered plugin', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        PluginManager.extend('NonExistentPlugin', 'NewPlugin', FooPluginClass, '.test-class');

        expect(consoleSpy).toHaveBeenCalledWith('Trying to extend non-registered plugin "NonExistentPlugin". The plugin will not be extended.');
        PluginManager.deregister('NewPlugin', '.test-class');
        jest.resetAllMocks();
    });

    it('should warn when calling getPlugin with no plugin name', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        const plugin = PluginManager.getPlugin();

        expect(plugin).toBeNull();
        expect(consoleSpy).toHaveBeenCalledWith('No plugin name was provided while trying to call getPlugin().');
        jest.resetAllMocks();
    });

    it('should warn when calling getPlugin with non-registered plugin name in strict mode', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        const plugin = PluginManager.getPlugin('NonExistentPlugin', true);

        expect(plugin).toBeNull();
        expect(consoleSpy).toHaveBeenCalledWith('The plugin "NonExistentPlugin" is not registered. You might need to register it first.');
        jest.resetAllMocks();
    });

    it('should warn when calling getPluginInstancesFromElement with non-HTML element', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        PluginManager.getPluginInstancesFromElement('not-an-element');

        expect(consoleSpy).toHaveBeenCalledWith('Passed element in getPluginInstancesFromElement() is not an Html element!');
        jest.resetAllMocks();
    });

    describe('initializePluginsInParentElement', () => {
        it('should initialize plugins only within parent element', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="test-plugin-inside"></div>
                </div>
                <div class="test-plugin-outside"></div>
            `;

            const parentElement = document.querySelector('.parent');

            PluginManager.register('ScopedPlugin', FooPluginClass, '.test-plugin-inside');
            PluginManager.register('OutsidePlugin', FooPluginClass, '.test-plugin-outside');

            await PluginManager.initializePluginsInParentElement(parentElement);

            // Plugin inside parent should be initialized
            expect(PluginManager.getPluginInstances('ScopedPlugin').length).toBe(1);
            expect(PluginManager.getPluginInstances('ScopedPlugin')[0]._initialized).toBe(true);

            // Plugin outside parent should not be initialized
            expect(PluginManager.getPluginInstances('OutsidePlugin').length).toBe(0);

            PluginManager.deregister('ScopedPlugin', '.test-plugin-inside');
            PluginManager.deregister('OutsidePlugin', '.test-plugin-outside');
        });

        it('should not initialize plugins with selectors not in parent element', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="inside"></div>
                </div>
                <div class="outside"></div>
            `;

            const parentElement = document.querySelector('.parent');

            PluginManager.register('OutsideOnly', FooPluginClass, '.outside');

            await PluginManager.initializePluginsInParentElement(parentElement);

            expect(PluginManager.getPluginInstances('OutsideOnly').length).toBe(0);

            PluginManager.deregister('OutsideOnly', '.outside');
        });

        it('should initialize plugins with various selector types within parent', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="test-class"></div>
                    <div id="test-id-scoped"></div>
                    <div data-scoped-plugin="true"></div>
                </div>
            `;

            const parentElement = document.querySelector('.parent');

            PluginManager.register('ClassPlugin', FooPluginClass, '.test-class');
            PluginManager.register('IdPlugin', FooPluginClass, '#test-id-scoped');
            PluginManager.register('DataPlugin', FooPluginClass, '[data-scoped-plugin]');

            await PluginManager.initializePluginsInParentElement(parentElement);

            expect(PluginManager.getPluginInstances('ClassPlugin').length).toBe(1);
            expect(PluginManager.getPluginInstances('IdPlugin').length).toBe(1);
            expect(PluginManager.getPluginInstances('DataPlugin').length).toBe(1);

            PluginManager.deregister('ClassPlugin', '.test-class');
            PluginManager.deregister('IdPlugin', '#test-id-scoped');
            PluginManager.deregister('DataPlugin', '[data-scoped-plugin]');
        });

        it('should initialize plugin registered with Node selector within parent', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="node-element"></div>
                </div>
            `;

            const parentElement = document.querySelector('.parent');
            const nodeElement = document.querySelector('.node-element');

            PluginManager.register('NodePlugin', FooPluginClass, nodeElement);

            await PluginManager.initializePluginsInParentElement(parentElement);

            expect(PluginManager.getPluginInstances('NodePlugin').length).toBe(1);
            expect(PluginManager.getPluginInstances('NodePlugin')[0]._initialized).toBe(true);

            PluginManager.deregister('NodePlugin', nodeElement);
        });

        it('should not initialize plugin registered with Node selector outside parent', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="inside"></div>
                </div>
                <div class="outside-node"></div>
            `;

            const parentElement = document.querySelector('.parent');
            const outsideNode = document.querySelector('.outside-node');

            PluginManager.register('OutsideNodePlugin', FooPluginClass, outsideNode);

            await PluginManager.initializePluginsInParentElement(parentElement);

            expect(PluginManager.getPluginInstances('OutsideNodePlugin').length).toBe(0);

            PluginManager.deregister('OutsideNodePlugin', outsideNode);
        });

        it('should initialize async plugins only within parent element', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="async-inside"></div>
                </div>
                <div class="async-outside"></div>
            `;

            const parentElement = document.querySelector('.parent');

            const asyncImport = new Promise((resolve) => {
                resolve({ default: AsyncPluginClass });
            });

            PluginManager.register('AsyncInsidePlugin', () => asyncImport, '.async-inside');
            PluginManager.register('AsyncOutsidePlugin', () => asyncImport, '.async-outside');

            await PluginManager.initializePluginsInParentElement(parentElement);

            expect(PluginManager.getPluginInstances('AsyncInsidePlugin').length).toBe(1);
            expect(PluginManager.getPluginInstances('AsyncInsidePlugin')[0]._initialized).toBe(true);

            expect(PluginManager.getPluginInstances('AsyncOutsidePlugin').length).toBe(0);

            PluginManager.deregister('AsyncInsidePlugin', '.async-inside');
            PluginManager.deregister('AsyncOutsidePlugin', '.async-outside');
        });

        it('should call update on existing plugin instances within parent', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="update-test"></div>
                </div>
            `;

            const parentElement = document.querySelector('.parent');

            PluginManager.register('UpdateTestPlugin', FooPluginClass, '.update-test');

            // First initialization
            await PluginManager.initializePlugins();

            expect(PluginManager.getPluginInstances('UpdateTestPlugin').length).toBe(1);

            const instance = PluginManager.getPluginInstances('UpdateTestPlugin')[0];
            const updateSpy = jest.spyOn(instance, '_update');

            // Second initialization scoped to parent
            await PluginManager.initializePluginsInParentElement(parentElement);

            // Should call update on existing instance
            expect(updateSpy).toHaveBeenCalledTimes(1);
            expect(PluginManager.getPluginInstances('UpdateTestPlugin').length).toBe(1);

            PluginManager.deregister('UpdateTestPlugin', '.update-test');
        });

        it('should not call update on existing plugin instances outside parent', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="inside"></div>
                </div>
                <div class="update-outside"></div>
            `;

            const parentElement = document.querySelector('.parent');

            PluginManager.register('OutsideUpdatePlugin', FooPluginClass, '.update-outside');

            // First initialization
            await PluginManager.initializePlugins();

            expect(PluginManager.getPluginInstances('OutsideUpdatePlugin').length).toBe(1);

            const instance = PluginManager.getPluginInstances('OutsideUpdatePlugin')[0];
            const updateSpy = jest.spyOn(instance, '_update');

            // Second initialization scoped to parent
            await PluginManager.initializePluginsInParentElement(parentElement);

            // Should NOT call update on instance outside parent
            expect(updateSpy).not.toHaveBeenCalled();

            PluginManager.deregister('OutsideUpdatePlugin', '.update-outside');
        });

        it('should initialize multiple instances within parent when selector matches multiple elements', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="multi-plugin"></div>
                    <div class="multi-plugin"></div>
                    <div class="multi-plugin"></div>
                </div>
                <div class="multi-plugin"></div>
            `;

            const parentElement = document.querySelector('.parent');

            PluginManager.register('MultiPlugin', FooPluginClass, '.multi-plugin');

            await PluginManager.initializePluginsInParentElement(parentElement);

            // Should initialize 3 instances (inside parent), not 4 (total in DOM)
            expect(PluginManager.getPluginInstances('MultiPlugin').length).toBe(3);

            PluginManager.deregister('MultiPlugin', '.multi-plugin');
        });

        it('should handle nested parent elements correctly', async () => {
            document.body.innerHTML = `
                <div class="outer-parent">
                    <div class="inner-parent">
                        <div class="nested-plugin"></div>
                    </div>
                    <div class="nested-plugin"></div>
                </div>
                <div class="nested-plugin"></div>
            `;

            const innerParent = document.querySelector('.inner-parent');

            PluginManager.register('NestedPlugin', FooPluginClass, '.nested-plugin');

            await PluginManager.initializePluginsInParentElement(innerParent);

            // Should initialize only 1 instance (inside inner-parent), not 3 (total in DOM)
            expect(PluginManager.getPluginInstances('NestedPlugin').length).toBe(1);

            PluginManager.deregister('NestedPlugin', '.nested-plugin');
        });

        it('should work with complex selectors', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="complex test-class" data-plugin="true"></div>
                </div>
                <div class="complex test-class" data-plugin="true"></div>
            `;

            const parentElement = document.querySelector('.parent');

            PluginManager.register('ComplexPlugin', FooPluginClass, '.complex.test-class[data-plugin="true"]');

            await PluginManager.initializePluginsInParentElement(parentElement);

            expect(PluginManager.getPluginInstances('ComplexPlugin').length).toBe(1);

            PluginManager.deregister('ComplexPlugin', '.complex.test-class[data-plugin="true"]');
        });

        it('should filter NodeList registrations to only initialize elements within parent', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="nodelist-plugin"></div>
                    <div class="nodelist-plugin"></div>
                </div>
                <div class="nodelist-plugin"></div>
                <div class="nodelist-plugin"></div>
            `;

            const parentElement = document.querySelector('.parent');
            const allElements = document.querySelectorAll('.nodelist-plugin');

            // Register with NodeList directly (not a string selector)
            PluginManager.register('NodeListPlugin', FooPluginClass, allElements);

            await PluginManager.initializePluginsInParentElement(parentElement);

            // Should only initialize 2 instances (inside parent), not 4 (total in NodeList)
            expect(PluginManager.getPluginInstances('NodeListPlugin').length).toBe(2);

            PluginManager.deregister('NodeListPlugin', allElements);
        });

        it('should not call update on NodeList registrations outside parent', async () => {
            document.body.innerHTML = `
                <div class="parent">
                    <div class="nodelist-update"></div>
                </div>
                <div class="nodelist-update"></div>
            `;

            const parentElement = document.querySelector('.parent');
            const allElements = document.querySelectorAll('.nodelist-update');

            PluginManager.register('NodeListUpdatePlugin', FooPluginClass, allElements);

            // First initialization
            await PluginManager.initializePlugins();

            expect(PluginManager.getPluginInstances('NodeListUpdatePlugin').length).toBe(2);

            const instances = PluginManager.getPluginInstances('NodeListUpdatePlugin');
            const updateSpies = instances.map(instance => jest.spyOn(instance, '_update'));

            // Second initialization scoped to parent
            await PluginManager.initializePluginsInParentElement(parentElement);

            // Only the instance inside parent should have _update called
            expect(updateSpies[0]).toHaveBeenCalledTimes(1);
            expect(updateSpies[1]).not.toHaveBeenCalled();

            PluginManager.deregister('NodeListUpdatePlugin', allElements);
        });
    });
});

