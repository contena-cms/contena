import Plugin from 'src/plugin-system/plugin.class';

/**
 * Region-tree adaptation of the upstream country-state select plugin.
 */
export default class FormRegionSelectPlugin extends Plugin {
    static options = {
        countrySelectSelector: '.country-select',
        regionSelectSelector: '.region-select',
        regionValueSelector: '.region-value',
        initialRegionPathAttribute: 'data-initial-region-path',
    };

    init() {
        this.countrySelect = this.el.querySelector(this.options.countrySelectSelector);
        this.regionSelects = Array.from(this.el.querySelectorAll(this.options.regionSelectSelector));
        this.regionValue = this.el.querySelector(this.options.regionValueSelector);
        this.initialPath = (this.el.getAttribute(this.options.initialRegionPathAttribute) || '')
            .split('|')
            .filter(Boolean);

        this.countrySelect.addEventListener('change', this._onCountryChange.bind(this));
        this.regionSelects.forEach((select, index) => select.addEventListener('change', () => this._onRegionChange(index)));

        if (this.countrySelect.value) {
            this._loadLevel(0, null);
        }
    }

    _onCountryChange() {
        this.initialPath = [];
        this.regionValue.value = '';
        this._resetLevels(0);

        if (this.countrySelect.value) {
            this._loadLevel(0, null);
        }
    }

    _onRegionChange(index) {
        const selectedId = this.regionSelects[index].value;
        this.initialPath = [];
        this.regionValue.value = selectedId;
        this._resetLevels(index + 1);

        if (selectedId && index + 1 < this.regionSelects.length) {
            this._loadLevel(index + 1, selectedId);
        }
    }

    _loadLevel(index, parentId) {
        const parameters = new URLSearchParams({countryId: this.countrySelect.value});
        if (parentId) {
            parameters.set('parentId', parentId);
        }

        fetch(`${window.router['frontend.country.region-data']}?${parameters}`, {
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json'},
        })
            .then(response => response.json())
            .then(content => this._updateSelect(index, content.regions));
    }

    _updateSelect(index, regions) {
        const select = this.regionSelects[index];
        select.querySelectorAll('option:not([data-placeholder-option="true"])').forEach(option => option.remove());

        regions.forEach(region => {
            const option = document.createElement('option');
            option.value = region.id;
            option.textContent = region.translated.name;
            select.append(option);
        });

        select.disabled = regions.length === 0;
        select.closest('.form-group')?.classList.toggle('d-none', regions.length === 0);

        const selectedId = this.initialPath[index];
        if (!selectedId || !regions.some(region => region.id === selectedId)) {
            return;
        }

        select.value = selectedId;
        this.regionValue.value = selectedId;
        if (index + 1 < this.regionSelects.length) {
            this._loadLevel(index + 1, selectedId);
        }
    }

    _resetLevels(startIndex) {
        this.regionSelects.slice(startIndex).forEach(select => {
            select.value = '';
            select.disabled = true;
            select.closest('.form-group')?.classList.add('d-none');
        });
    }
}
