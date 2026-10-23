/**
 * @sw-package framework
 */
import type { CurrencyOptions } from 'src/core/service/utils/format.utils';

const { currency } = Contena.Utils.format;

/**
 * @private
 */
Contena.Filter.register(
    'currency',
    (value: string | boolean, format: string, decimalPlaces: number, additionalOptions: CurrencyOptions) => {
        if (
            (!value || value === true) &&
            (!Contena.Utils.types.isNumber(value) || Contena.Utils.types.isEqual(value, NaN))
        ) {
            return '-';
        }

        if (Contena.Utils.types.isEqual(parseInt(value, 10), NaN)) {
            return value;
        }

        return currency(parseFloat(value), format, decimalPlaces, additionalOptions);
    },
);
