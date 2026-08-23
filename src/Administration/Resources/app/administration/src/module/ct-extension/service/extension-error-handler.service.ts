type MappedError = {
    title: string;
    message: string;
    details: string | null | undefined;
};

class PluginError {
    constructor(
        public readonly title: string,
        public readonly message: string,
    ) {}
}

const errorCodes: { [key: string]: PluginError } = {
    FRAMEWORK__PLUGIN_NO_PLUGIN_FOUND_IN_ZIP: new PluginError(
        'global.default.error',
        'ct-extension.errors.messageUploadFailureNoPluginFoundInZipFile',
    ),
    FRAMEWORK__PLUGIN_NOT_A_ZIP_FILE: new PluginError(
        'global.default.error',
        'ct-extension.errors.messageUploadFailureNotAZipFile',
    ),
    FRAMEWORK__PLUGIN_EXTRACTION_FAILED: new PluginError(
        'global.default.error',
        'ct-extension.errors.messageUploadFailureUnzipFailed',
    ),
    FRAMEWORK__PLUGIN_BASE_CLASS_NOT_FOUND: new PluginError(
        'global.default.error',
        'ct-extension.errors.messagePluginBaseClassNotFound',
    ),
    FRAMEWORK__PLUGIN_REQUIREMENT_MISMATCH: new PluginError(
        'global.default.error',
        'ct-extension.errors.messagePluginRequirementMismatch',
    ),
};

function getNotification(error: ContenaHttpError): MappedError {
    if (typeof errorCodes[error.code] !== 'undefined') {
        return {
            title: errorCodes[error.code].title,
            message: errorCodes[error.code].message,
            details: error.detail,
        };
    }

    return {
        title: 'global.default.error',
        message: 'ct-extension.errors.messageGenericFailure',
        details: error.detail,
    };
}

function mapErrors(errors: ContenaHttpError[]) {
    return errors.map(getNotification);
}

/**
 * @private
 */
export default {
    mapErrors,
};

/**
 * @private
 */
export type { MappedError };
