import FilterBooleanPlugin from 'src/plugin/listing/filter-boolean.plugin';

describe('FilterBoolean tests', () => {
    let filterBooleanPlugin;

    beforeEach(() => {
        document.body.innerHTML = `
        <div class="filter-boolean filter-panel-item" role="listitem" aria-busy="true" data-filter-boolean="true">
            <div class="form-check">
                <input type="checkbox" class="filter-boolean-input form-check-input" id="featured" name="featured" disabled data-filter-loading>
                    <label for="featured" class="filter-boolean-label custom-control-label">
                        <span class="filter-boolean-alt-text visually-hidden">Add filter: Featured</span>
                        <span aria-hidden="true">Featured</span>
                    </label>
            </div>
        </div>

        <div class="content-blog-listing-wrapper"></div>
        `;

        // Mock the instance call of the listing plugin
        window.PluginManager.getPluginInstanceFromElement = (element, pluginName) => {
            if (pluginName === 'Listing') {
                return new class MockListingPlugin {
                    registerFilter() {}
                };
            }

            return {};
        };

        filterBooleanPlugin = new FilterBooleanPlugin(document.querySelector('[data-filter-boolean="true"]'), {
            name: 'featured',
            displayName: 'Featured',
            snippets: {
                altText: 'Add filter: Featured',
                altTextActive: 'Remove filter: Featured',
            },
        });
    });

    test('filter boolean plugin exists', () => {
        expect(typeof filterBooleanPlugin).toBe('object');
    });

    test('enables the filter after its event handler is registered', () => {
        const filterElement = document.querySelector('[data-filter-boolean="true"]');
        const input = document.querySelector('.filter-boolean-input');

        expect(filterElement.getAttribute('aria-busy')).toBeNull();
        expect(input.getAttribute('disabled')).toBeNull();
        expect(input.getAttribute('data-filter-loading')).toBeNull();
    });

    test('should return correct values depending on checkbox state', () => {
        document.querySelector('.filter-boolean-input').checked = true;
        expect(filterBooleanPlugin.getValues()).toEqual({ 'featured': '1' });

        document.querySelector('.filter-boolean-input').checked = false;
        expect(filterBooleanPlugin.getValues()).toEqual({ 'featured': '' });
    });

    test('should render the correct alt text depending on checkbox state', () => {
        document.querySelector('.filter-boolean-input').checked = true;
        filterBooleanPlugin._updateAltText();

        expect(document.querySelector('.filter-boolean-alt-text').textContent).toBe('Remove filter: Featured');

        document.querySelector('.filter-boolean-input').checked = false;
        filterBooleanPlugin._updateAltText();

        expect(document.querySelector('.filter-boolean-alt-text').textContent).toBe('Add filter: Featured');
    });
});
