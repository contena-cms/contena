import FormRegionSelectPlugin from 'src/plugin/forms/form-region-select.plugin';

describe('Form region select plugin', () => {
    function template(initialPath = '') {
        return `
            <form id="addressForm" data-initial-region-path="${initialPath}">
                <select class="country-select">
                    <option value="">Select country</option>
                    <option value="CN" selected>China</option>
                </select>
                <input class="region-value" value="">
                <div class="form-group">
                    <select class="region-select">
                        <option value="" data-placeholder-option="true">Select province</option>
                    </select>
                </div>
                <div class="form-group d-none">
                    <select class="region-select" disabled>
                        <option value="" data-placeholder-option="true">Select city</option>
                    </select>
                </div>
                <div class="form-group d-none">
                    <select class="region-select" disabled>
                        <option value="" data-placeholder-option="true">Select district</option>
                    </select>
                </div>
            </form>
        `;
    }

    function response(regions) {
        return Promise.resolve({
            json: () => Promise.resolve({ regions }),
        });
    }

    function createPlugin() {
        return new FormRegionSelectPlugin(document.querySelector('#addressForm'));
    }

    async function flushPromises() {
        await new Promise(process.nextTick);
    }

    beforeEach(() => {
        document.body.innerHTML = template();
        window.router = {
            'frontend.country.region-data': '/country/region-data',
        };
        global.fetch = jest.fn(() => response([
            { id: 'province-id', translated: { name: 'Province' } },
        ]));
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('loads and renders root regions for the initial country', async () => {
        const plugin = createPlugin();
        await flushPromises();

        expect(plugin).toBeInstanceOf(FormRegionSelectPlugin);
        expect(global.fetch).toHaveBeenCalledWith(
            '/country/region-data?countryId=CN',
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                },
            },
        );

        const provinceSelect = document.querySelectorAll('.region-select')[0];
        expect(provinceSelect.disabled).toBe(false);
        expect(provinceSelect.options).toHaveLength(2);
        expect(provinceSelect.options[1].textContent).toBe('Province');
    });

    it('does not request regions when the country selection is cleared', async () => {
        createPlugin();
        await flushPromises();
        global.fetch.mockClear();

        const countrySelect = document.querySelector('.country-select');
        countrySelect.value = '';
        countrySelect.dispatchEvent(new Event('change'));

        expect(global.fetch).not.toHaveBeenCalled();
        expect(document.querySelector('.region-value').value).toBe('');
        document.querySelectorAll('.region-select').forEach(select => expect(select.disabled).toBe(true));
    });

    it('loads each child level and stores the deepest selected region', async () => {
        global.fetch
            .mockImplementationOnce(() => response([
                { id: 'province-id', translated: { name: 'Province' } },
            ]))
            .mockImplementationOnce(() => response([
                { id: 'city-id', translated: { name: 'City' } },
            ]))
            .mockImplementationOnce(() => response([
                { id: 'district-id', translated: { name: 'District' } },
            ]));

        createPlugin();
        await flushPromises();

        const selects = document.querySelectorAll('.region-select');
        selects[0].value = 'province-id';
        selects[0].dispatchEvent(new Event('change'));
        await flushPromises();

        expect(global.fetch).toHaveBeenNthCalledWith(
            2,
            '/country/region-data?countryId=CN&parentId=province-id',
            expect.any(Object),
        );
        expect(selects[1].disabled).toBe(false);

        selects[1].value = 'city-id';
        selects[1].dispatchEvent(new Event('change'));
        await flushPromises();

        expect(global.fetch).toHaveBeenNthCalledWith(
            3,
            '/country/region-data?countryId=CN&parentId=city-id',
            expect.any(Object),
        );

        selects[2].value = 'district-id';
        selects[2].dispatchEvent(new Event('change'));

        expect(document.querySelector('.region-value').value).toBe('district-id');
    });

    it('restores an initial province, city, and district path', async () => {
        document.body.innerHTML = template('province-id|city-id|district-id');
        global.fetch
            .mockImplementationOnce(() => response([
                { id: 'province-id', translated: { name: 'Province' } },
            ]))
            .mockImplementationOnce(() => response([
                { id: 'city-id', translated: { name: 'City' } },
            ]))
            .mockImplementationOnce(() => response([
                { id: 'district-id', translated: { name: 'District' } },
            ]));

        createPlugin();
        await flushPromises();
        await flushPromises();
        await flushPromises();

        const selects = document.querySelectorAll('.region-select');
        expect(Array.from(selects, select => select.value)).toEqual([
            'province-id',
            'city-id',
            'district-id',
        ]);
        expect(document.querySelector('.region-value').value).toBe('district-id');
    });

    it('hides a level when the route returns no regions', async () => {
        global.fetch = jest.fn(() => response([]));

        createPlugin();
        await flushPromises();

        const provinceSelect = document.querySelectorAll('.region-select')[0];
        expect(provinceSelect.disabled).toBe(true);
        expect(provinceSelect.closest('.form-group').classList.contains('d-none')).toBe(true);
    });
});
