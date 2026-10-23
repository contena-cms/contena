import Plugin from 'src/plugin-system/plugin.class';
import SessionStorage from 'src/helper/storage/session-storage.helper';

const CUSTOMER_COMMENT_KEY = 'userComment';
const TOS_KEY = 'tos';
const REVOCATION_KEY = 'revocation';

/**
 * Persists checkout user-specific form data in the session storage, so it survives
 * the page reloads within a checkout without leaking across account switches or
 * outliving the browsing session it was entered in.
 *
 * @sw-package checkout
 */
export default class CheckoutCustomerStoragePlugin extends Plugin {
    static options = {
        userId: null,
        storageKey: 'checkoutCustomerStorage',
    };

    init() {
        if (!this.options.userId) {
            return;
        }

        this._form = this.el.tagName === 'FORM'
            ? this.el
            : (this.el.form ?? document.getElementById(this.el.getAttribute('form')));

        if (!this._form) {
            return;
        }

        this._fieldDefinitions = this._getFieldDefinitions();
        this._fields = this._resolveFields();
        this._clearCurrentCustomerData = this._clearCurrentCustomerData.bind(this);

        this._restoreCurrentCustomerData();
        this._registerEvents();
    }

    _registerEvents() {
        this._fields.forEach((field) => {
            field.events.forEach((eventName) => {
                field.element.addEventListener(eventName, () => this._updateField(field));
            });
        });

        this._form.addEventListener('submit', this._clearCurrentCustomerData);
        this._form.addEventListener('reset', this._clearCurrentCustomerData);
    }

    _restoreCurrentCustomerData() {
        const userData = this._getCurrentCustomerData();

        this._fields.forEach((field) => {
            field.writeValue(field.element, userData[field.key] ?? null);
        });
    }

    _getFieldDefinitions() {
        // Add new persisted checkout fields here by defining how to find, read, write, and normalize them.
        return [
            {
                key: CUSTOMER_COMMENT_KEY,
                resolveElement: () => this._getFormElementByName(CUSTOMER_COMMENT_KEY),
                events: ['input', 'change'],
                normalizeValue: (value) => typeof value === 'string' ? value : null,
                readValue: (element) => element.value === '' ? null : element.value,
                writeValue: (element, value) => {
                    if (typeof value === 'string') {
                        element.value = value;
                    }
                },
            },
            this._getCheckboxFieldDefinition(TOS_KEY),
            this._getCheckboxFieldDefinition(REVOCATION_KEY),
        ];
    }

    _getCheckboxFieldDefinition(key) {
        return {
            key,
            resolveElement: () => this._getFormElementByName(key),
            events: ['change'],
            normalizeValue: (value) => value === true ? true : null,
            readValue: (element) => element.checked ? true : null,
            writeValue: (element, value) => {
                element.checked = value === true;
            },
        };
    }

    _resolveFields() {
        return this._fieldDefinitions.reduce((fields, fieldDefinition) => {
            const element = fieldDefinition.resolveElement();

            if (!element) {
                return fields;
            }

            fields.push({
                ...fieldDefinition,
                element,
            });

            return fields;
        }, []);
    }

    _updateField(field) {
        this._updateCurrentCustomerData((userData) => {
            const value = field.readValue(field.element);

            if (value === null) {
                delete userData[field.key];

                return;
            }

            userData[field.key] = value;
        });
    }

    _clearCurrentCustomerData() {
        const storedCustomers = this._getStoredCustomers();

        if (!(this.options.userId in storedCustomers)) {
            return;
        }

        delete storedCustomers[this.options.userId];
        this._setStoredCustomers(storedCustomers);
    }

    _updateCurrentCustomerData(updater) {
        const storedCustomers = this._getStoredCustomers();
        const userData = this._getCurrentCustomerData(storedCustomers);

        updater(userData);

        if (Object.keys(userData).length === 0) {
            delete storedCustomers[this.options.userId];
        } else {
            storedCustomers[this.options.userId] = userData;
        }

        this._setStoredCustomers(storedCustomers);
    }

    _getCurrentCustomerData(storedCustomers = this._getStoredCustomers()) {
        return this._normalizeCustomerData(storedCustomers[this.options.userId]) ?? {};
    }

    _getStoredCustomers() {
        return this._getParsedStorage(this.options.storageKey).users;
    }

    _getParsedStorage(storageKey) {
        const storedValue = SessionStorage.getItem(storageKey);

        if (typeof storedValue !== 'string' || storedValue === '') {
            return {
                users: {},
                valid: false,
            };
        }

        try {
            const parsedValue = JSON.parse(storedValue);

            if (parsedValue && typeof parsedValue === 'object' && !Array.isArray(parsedValue)) {
                return {
                    users: this._normalizeStoredCustomers(parsedValue),
                    valid: true,
                };
            }
        } catch (error) {
            return {
                users: {},
                valid: false,
            };
        }

        return {
            users: {},
            valid: false,
        };
    }

    _normalizeStoredCustomers(storedCustomers) {
        return Object.entries(storedCustomers).reduce((normalizedCustomers, [userId, userData]) => {
            const normalizedCustomerData = this._normalizeCustomerData(userData);

            if (normalizedCustomerData && Object.keys(normalizedCustomerData).length > 0) {
                normalizedCustomers[userId] = normalizedCustomerData;
            }

            return normalizedCustomers;
        }, {});
    }

    _normalizeCustomerData(userData) {
        if (!userData || typeof userData !== 'object' || Array.isArray(userData)) {
            return null;
        }

        return this._fieldDefinitions.reduce((normalizedCustomerData, fieldDefinition) => {
            const normalizedValue = fieldDefinition.normalizeValue(userData[fieldDefinition.key]);

            if (normalizedValue !== null) {
                normalizedCustomerData[fieldDefinition.key] = normalizedValue;
            }

            return normalizedCustomerData;
        }, {});
    }

    _getFormElementByName(name) {
        return Array.from(document.getElementsByName(name)).find((element) => element.form === this._form) ?? null;
    }

    _setStoredCustomers(storedCustomers) {
        if (Object.keys(storedCustomers).length === 0) {
            SessionStorage.removeItem(this.options.storageKey);

            return;
        }

        SessionStorage.setItem(this.options.storageKey, JSON.stringify(storedCustomers));
    }
}
