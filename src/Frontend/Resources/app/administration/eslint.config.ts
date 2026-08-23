/**
 * Reuses the Administration ESLint flat config for Frontend administration sources.
 *
 * Frontend administration tests are linted from the same rule entrypoint so shared aliases,
 * parser options, and Contena rule compatibility stay aligned.
 */
export { default } from '../../../../Administration/Resources/app/administration/eslint.config.ts';
