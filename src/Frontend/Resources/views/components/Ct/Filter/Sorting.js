export default class FilterSorting extends ContenaComponent {

    init() {
        this.registerEvents();
    }

    registerEvents() {
        this.el.addEventListener('change', this.handleChange.bind(this));
    }

    handleChange(event) {
        const sorting = event.target.value;

        Contena.emit('FilterSorting:Change', sorting);

        this.dispatchEvent('FilterSorting:Change', { sorting });
    }

    destroy() {
        this.el.removeEventListener('change', this.handleChange.bind(this));
    }
}
