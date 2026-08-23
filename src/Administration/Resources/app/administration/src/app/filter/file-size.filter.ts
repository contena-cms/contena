/**
 * @private
 */
Contena.Filter.register('fileSize', (value: number, locale: string) => {
    if (!value) {
        return '';
    }

    return Contena.Utils.format.fileSize(value, locale);
});

/* @private */
export {};
