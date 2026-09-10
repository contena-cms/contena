export default class ActiveFilters extends ContenaComponent {

    init() {
        this.activeFilters = new Map();
        this.resetAllButton = this.el.querySelector('.ct-active-filters__btn-reset-all');

        this.registerEvents();
    }

    registerEvents() {
        Contena.on('Filter:Change', this.handleFilterChange.bind(this));
        Contena.on('Filter:Init', this.handleFilterInit.bind(this));

        this.resetAllButton.addEventListener('click', this.resetAll.bind(this));
    }

    resetAll() {
        this.activeFilters.forEach(filter => {
            const eventData = { paramName: filter.paramName, option: filter.option };

            this.removeActiveFilterLabel(eventData);
            Contena.emit('Filter:Remove', eventData);
            Contena.emit(`Filter:Remove:${filter.paramName}`, eventData);
        });

        Contena.emit('Filter:ResetAll');
        this.updateResetAllButton();
    }

    handleFilterChange({ paramName, value, label, option }) {
        if (value === null ||
            value === undefined ||
            value === '' ||
            value === false ||
            value.length === 0) {
            this.removeActiveFilterLabel({ paramName, label, option });
        } else {
            this.updateActiveFilterLabel({ paramName, label, option });
        }

        this.updateResetAllButton();
    }

    handleFilterInit({ paramName, value, label, option }) {
        if (value === null ||
            value === undefined ||
            value === '' ||
            value === false ||
            value.length === 0) {
            return;
        }

        this.updateActiveFilterLabel({ paramName, label, option });
        this.updateResetAllButton();
    }

    updateActiveFilterLabel({ paramName, label, option }) {
        const key = option ? `${paramName}-${option}` : paramName;
        let filter = this.activeFilters.get(key);

        if (filter) {
            const labelElement = filter.el.querySelector('.ct-active-filter__label');
            if (labelElement) {
                labelElement.innerHTML = label;
            }
            filter.label = label;
        } else {
            filter = {
                paramName,
                label,
                option,
                el: this.createActiveFilterEl({ paramName, label, option }),
            };

            this.activeFilters.set(key, filter);
            this.el.prepend(filter.el);

            filter.el.addEventListener('click', this.handleActiveFilterClick.bind(this, { paramName, option }));
        }
    }

    removeActiveFilterLabel({ paramName, option }) {
        const key = option ? `${paramName}-${option}` : paramName;
        const existingFilter = this.activeFilters.get(key);

        if (existingFilter) {
            existingFilter.el.removeEventListener('click', this.handleActiveFilterClick.bind(this, { paramName, option }));
            existingFilter.el.remove();
            this.activeFilters.delete(key);
        }
    }

    handleActiveFilterClick({ paramName, option }) {
        this.removeActiveFilterLabel({ paramName, option });
        this.updateResetAllButton();

        Contena.emit('Filter:Remove', { paramName, option });
        Contena.emit(`Filter:Remove:${paramName}`, { paramName, option });
    }

    updateResetAllButton() {
        if (this.activeFilters.size > 0) {
            this.resetAllButton.classList.remove('is--hidden');
        } else {
            this.resetAllButton.classList.add('is--hidden');
        }
    }

    createActiveFilterEl({ paramName, label, option }) {
        const element = document.createElement('button');

        element.classList.add('ct-active-filter__item', 'btn');
        element.setAttribute('data-filter', paramName);
        if (option) {
            element.setAttribute('data-option', option);
        }

        element.innerHTML = `
            <span class="ct-active-filter__label">${label.trim()}</span>
            <span class="ct-active-filter__icon-remove" aria-hidden="true">×</span>
        `;

        return element;
    }

    destroy() {
        this.resetAllButton.removeEventListener('click', this.resetAll.bind(this));

        Contena.off('Filter:Change', this.handleFilterChange.bind(this));
        Contena.off('Filter:Init', this.handleFilterInit.bind(this));
    }
}
