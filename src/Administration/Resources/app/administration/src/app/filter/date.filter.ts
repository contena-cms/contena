Contena.Filter.register('date', (value: string, options: Intl.DateTimeFormatOptions = {}): string => {
    if (!value) {
        return '';
    }

    return Contena.Utils.format.date(value, options);
});

/**
 * @private
 */
export default {};
