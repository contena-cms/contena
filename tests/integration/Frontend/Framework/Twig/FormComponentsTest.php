<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Twig;

use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Twig\Environment;

/**
 * In production `formViolations` reaches the components as a Twig global
 * (`TemplateDataExtension::getGlobals()`). The tag-syntax tests below put it in the render context
 * instead, which lands in the same place, so the violation path is covered end to end.
 *
 * @internal
 */
class FormComponentsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testInputRendersLabelAndValidationHooks(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'id' => 'name',
            'label' => 'Name',
            'validationRules' => 'required',
        ]);

        static::assertStringContainsString('class="ct-form-input ct-form-field"', $html);
        static::assertStringContainsString('class="ct-form-label form-label" for="name"', $html);
        static::assertStringContainsString('Name', $html);
        static::assertStringContainsString('class="ct-form-input__control ct-form-field__control form-control"', $html);
        static::assertStringContainsString('id="name"', $html);
        static::assertStringContainsString('name="name"', $html);
        static::assertStringContainsString('type="text"', $html);

        // The client validation reads the rules from the field and writes its messages into the
        // element referenced by aria-describedby, which it locates by the "feedback" in its id.
        static::assertStringContainsString('data-validation="required"', $html);
        static::assertStringContainsString('aria-required="true"', $html);
        static::assertStringContainsString('aria-describedby="name-feedback"', $html);
        static::assertStringContainsString('class="ct-form-feedback" id="name-feedback"', $html);
    }

    /**
     * `setFieldRequired()` finds the marker through the label's `for`, and appends its own when
     * there is none.
     */
    public function testInputRendersTheRequiredMarkerOnlyForRequiredFields(): void
    {
        $required = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'label' => 'Name',
            'validationRules' => 'required',
        ]);

        $optional = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'label' => 'Name',
            'validationRules' => 'email',
        ]);

        $plain = $this->render('Ct:Form:Input', ['name' => 'name', 'label' => 'Name']);

        static::assertStringContainsString('class="ct-form-label__required form-required-label" aria-hidden="true"', $required);
        static::assertStringNotContainsString('form-required-label', $optional);
        static::assertStringNotContainsString('aria-required', $optional);
        static::assertStringContainsString('data-validation="email"', $optional);

        // The helper enrols every `[data-validation]` field, so a field with no rules must not
        // carry the attribute at all.
        static::assertStringNotContainsString('data-validation', $plain);
    }

    public function testInputLinksDescriptionAndFeedbackToTheControl(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'password',
            'id' => 'password',
            'label' => 'Password',
            'description' => 'At least 8 characters.',
        ]);

        static::assertStringContainsString('aria-describedby="password-description password-feedback"', $html);
        static::assertStringContainsString('class="ct-form-description form-text" id="password-description"', $html);
        static::assertStringContainsString('At least 8 characters.', $html);
    }

    public function testInputUsesTheAriaLabelWhenThereIsNoVisibleLabel(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'search',
            'aria-label' => 'Search term',
        ]);

        static::assertStringNotContainsString('<label', $html);
        static::assertStringContainsString('aria-label="Search term"', $html);
    }

    /**
     * The control is the primary element: `class` and `style` dress the field wrapper and
     * everything else lands on the control, so native input attributes need no props of their own.
     */
    public function testClassAndStyleDressTheWrapperAndEveryOtherAttributeReachesTheControl(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'class' => 'col-sm-6',
            'style' => 'order: 2',
            'placeholder' => 'Jane',
            'autocomplete' => 'section-personal given-name',
            'maxlength' => 32,
            'data-form-validation-equal' => 'passwordMatch',
        ]);

        static::assertStringContainsString('class="ct-form-input ct-form-field col-sm-6"', $html);
        static::assertMatchesRegularExpression('/<div[^>]*style="order: 2"/', $html);

        static::assertMatchesRegularExpression('/<input[^>]*placeholder="Jane"/', $html);
        static::assertMatchesRegularExpression('/<input[^>]*autocomplete="section-personal given-name"/', $html);
        static::assertMatchesRegularExpression('/<input[^>]*maxlength="32"/', $html);
        static::assertMatchesRegularExpression('/<input[^>]*data-form-validation-equal="passwordMatch"/', $html);

        static::assertDoesNotMatchRegularExpression('/<div[^>]*placeholder/', $html);
        static::assertDoesNotMatchRegularExpression('/<div[^>]*data-form-validation-equal/', $html);
        static::assertDoesNotMatchRegularExpression('/<input[^>]*style=/', $html);
    }

    /**
     * `class` is taken by the wrapper, so a class for the control goes through the `control:`
     * namespace, which `attributes.nested()` picks up.
     */
    public function testControlPrefixedClassAndStyleReachTheControl(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'class' => 'col-sm-6',
            'control:class' => 'is--custom',
            'control:style' => 'width: 4rem',
        ]);

        static::assertStringContainsString('class="ct-form-input ct-form-field col-sm-6"', $html);
        static::assertStringContainsString('class="ct-form-input__control ct-form-field__control form-control is--custom"', $html);
        static::assertMatchesRegularExpression('/<input[^>]*style="width: 4rem"/', $html);

        // The prefixed keys themselves must not leak into the markup.
        static::assertStringNotContainsString('control:', $html);
    }

    public function testInputRendersBooleanAttributesWithoutAValue(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'disabled' => true,
            'readonly' => true,
        ]);

        static::assertStringContainsString(' disabled', $html);
        static::assertStringContainsString(' readonly', $html);
    }

    /**
     * The predecessor dropped `value` for anything `empty()` considers falsy, which silently
     * blanked a "0" on re-render after a failed submit.
     */
    public function testInputKeepsAZeroValue(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'quantity',
            'type' => 'number',
            'value' => 0,
        ]);

        static::assertStringContainsString('value="0"', $html);
    }

    /**
     * The same field can appear twice on one page (a login form in the header and on the login
     * page), so a `name`-derived id would collide and break every `for` / `aria-describedby` that
     * resolves by id.
     */
    public function testAutoGeneratedIdsDifferBetweenRenders(): void
    {
        $first = $this->render('Ct:Form:Input', ['name' => 'email', 'label' => 'Email']);
        $second = $this->render('Ct:Form:Input', ['name' => 'email', 'label' => 'Email']);

        static::assertSame(1, preg_match('/<input[^>]* id="(email-\d+)"/', $first, $a));
        static::assertSame(1, preg_match('/<input[^>]* id="(email-\d+)"/', $second, $b));
        static::assertNotSame($a[1], $b[1]);

        // The label and the feedback element must follow the generated id, not the name.
        static::assertStringContainsString('for="' . $a[1] . '"', $first);
        static::assertStringContainsString('id="' . $a[1] . '-feedback"', $first);
        static::assertStringContainsString('aria-describedby="' . $a[1] . '-feedback"', $first);
    }

    public function testAnExplicitIdRipplesIntoTheLabelAndFeedbackReferences(): void
    {
        $explicit = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'id' => 'billingFirstName',
            'label' => 'Name',
        ]);

        static::assertStringContainsString('id="billingFirstName"', $explicit);
        static::assertStringContainsString('for="billingFirstName"', $explicit);
        static::assertStringContainsString('aria-describedby="billingFirstName-feedback"', $explicit);
    }

    public function testTextareaRendersTheValueAsItsContent(): void
    {
        $html = $this->render('Ct:Form:Textarea', [
            'name' => 'content',
            'label' => 'Your comment',
            'value' => 'Great article',
            'rows' => 4,
            'validationRules' => 'required,minLength',
            'minlength' => 40,
        ]);

        static::assertStringContainsString('rows="4"', $html);
        static::assertStringContainsString('minlength="40"', $html);
        static::assertStringContainsString('data-validation="required,minLength"', $html);
        static::assertStringContainsString('>Great article</textarea>', $html);
        static::assertStringContainsString('class="ct-form-textarea__control ct-form-field__control form-control"', $html);
    }

    public function testSelectRendersOptionsAndMarksTheSelectedOne(): void
    {
        $html = $this->render('Ct:Form:Select', [
            'name' => 'languageId',
            'label' => 'Language',
            'placeholder' => 'Please choose',
            'value' => 'zh',
            'options' => [
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'zh', 'label' => 'Chinese'],
                ['value' => 'default', 'label' => 'System default', 'disabled' => true],
            ],
        ]);

        static::assertStringContainsString('class="ct-form-select__control ct-form-field__control form-select"', $html);
        static::assertStringContainsString('<option value="">Please choose</option>', $html);
        static::assertStringContainsString('<option value="en">English</option>', $html);
        static::assertStringContainsString('<option value="zh" selected="selected">Chinese</option>', $html);
        static::assertStringContainsString('<option value="default" disabled>System default</option>', $html);
        static::assertSame(1, substr_count($html, 'selected="selected"'));
    }

    public function testCheckboxUsesTheBootstrapFormCheckStructure(): void
    {
        $html = $this->render('Ct:Form:Checkbox', [
            'name' => 'acceptedDataProtection',
            'id' => 'acceptedDataProtection',
            'label' => 'I have read the data protection information.',
            'checked' => true,
            'validationRules' => 'required',
        ]);

        static::assertStringContainsString('class="ct-form-checkbox ct-form-field form-check"', $html);
        static::assertStringContainsString('class="ct-form-checkbox__control ct-form-field__control form-check-input"', $html);
        static::assertStringContainsString('type="checkbox"', $html);
        static::assertStringContainsString('value="1"', $html);
        static::assertStringContainsString(' checked', $html);
        static::assertStringContainsString('class="ct-form-label form-check-label" for="acceptedDataProtection"', $html);
    }

    public function testRadioGroupWrapsTheOptionsInAFieldset(): void
    {
        $html = $this->render('Ct:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'id' => 'sizeChoice',
            'label' => 'Choose a size',
            'value' => 'md',
            'validationRules' => 'required',
            'options' => [
                ['value' => 'sm', 'label' => 'Small', 'id' => 'sizeChoice-sm'],
                ['value' => 'md', 'label' => 'Medium', 'id' => 'sizeChoice-md'],
            ],
        ]);

        static::assertStringContainsString('<fieldset ', $html);
        static::assertStringContainsString('class="ct-form-radio-group ct-form-field"', $html);
        static::assertStringContainsString('class="ct-form-fieldset-label form-label ct-form-radio-group__legend fs-5 fw-bold"', $html);
        static::assertStringContainsString('Choose a size', $html);
        static::assertSame(1, substr_count($html, 'form-required-label'));

        static::assertSame(2, substr_count($html, 'type="radio"'));
        static::assertSame(2, substr_count($html, 'name="sizeChoice"'));
        static::assertStringContainsString('id="sizeChoice-sm"', $html);
        static::assertStringContainsString('id="sizeChoice-md"', $html);

        // Every radio points at the single feedback element of the group.
        static::assertSame(2, substr_count($html, 'aria-describedby="sizeChoice-feedback"'));
        static::assertStringContainsString('id="sizeChoice-feedback"', $html);

        static::assertSame(1, substr_count($html, ' checked'));
        static::assertMatchesRegularExpression('/id="sizeChoice-md"[^>]* checked/', $html);
    }

    /**
     * The predecessor printed the marker into every legend, required or not.
     */
    public function testRadioGroupLeavesOutTheRequiredMarkerWhenNothingIsRequired(): void
    {
        $html = $this->render('Ct:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'label' => 'Choose a size',
            'options' => [['value' => 'sm', 'label' => 'Small']],
        ]);

        static::assertStringNotContainsString('form-required-label', $html);
    }

    public function testRadioRendersStandaloneWithItsOwnFormCheck(): void
    {
        $html = $this->render('Ct:Form:Radio', [
            'name' => 'sizeChoice',
            'value' => 'sm',
            'id' => 'sizeChoice-sm',
            'label' => 'Small',
        ]);

        static::assertStringContainsString('class="ct-form-radio form-check"', $html);
        static::assertStringContainsString('class="ct-form-radio__control ct-form-field__control form-check-input"', $html);

        // A standalone radio has no feedback element of its own, so it must not point at one.
        // Ct:Form:RadioGroup passes the id of its own feedback element down instead.
        static::assertStringNotContainsString('aria-describedby', $html);
        static::assertStringContainsString('class="ct-form-label form-check-label" for="sizeChoice-sm"', $html);
    }

    public function testBirthdaySelectRendersThreeAutocompletedSelects(): void
    {
        $html = $this->render('Ct:Form:BirthdaySelect', [
            'day' => 24,
            'month' => 7,
            'year' => 1990,
            'validationRules' => 'required',
        ]);

        static::assertStringContainsString('<fieldset ', $html);
        static::assertStringContainsString('class="ct-form-birthday-select"', $html);
        static::assertStringContainsString('class="ct-form-fieldset-label form-label"', $html);
        static::assertStringNotContainsString('<label', $html);

        static::assertStringContainsString('name="birthdayDay"', $html);
        static::assertStringContainsString('name="birthdayMonth"', $html);
        static::assertStringContainsString('name="birthdayYear"', $html);

        static::assertStringContainsString('id="birthdayDay-', $html);
        static::assertStringContainsString('id="birthdayMonth-', $html);
        static::assertStringContainsString('id="birthdayYear-', $html);

        static::assertStringContainsString('autocomplete="bday-day"', $html);
        static::assertStringContainsString('autocomplete="bday-month"', $html);
        static::assertStringContainsString('autocomplete="bday-year"', $html);

        // Each part carries its own name, because the legend alone does not distinguish them.
        static::assertSame(3, substr_count($html, 'aria-label="'));
        static::assertSame(3, substr_count($html, 'aria-required="true"'));

        static::assertStringContainsString('<option value="24" selected="selected">24</option>', $html);
        static::assertStringContainsString('<option value="7" selected="selected">7</option>', $html);
        static::assertStringContainsString('<option value="1990" selected="selected">1990</option>', $html);
        static::assertSame(3, substr_count($html, 'selected="selected"'));

        // Each part is validated on its own, so each needs a feedback element of its own.
        static::assertMatchesRegularExpression('/id="birthdayDay-\d+-feedback"/', $html);
        static::assertMatchesRegularExpression('/id="birthdayMonth-\d+-feedback"/', $html);
        static::assertMatchesRegularExpression('/id="birthdayYear-\d+-feedback"/', $html);
    }

    public function testBirthdaySelectPrefixesNamesAndIds(): void
    {
        $html = $this->render('Ct:Form:BirthdaySelect', [
            'namePrefix' => 'billingAddress',
            'idPrefix' => 'billing-',
        ]);

        static::assertStringContainsString('name="billingAddress[birthdayDay]"', $html);
        static::assertStringContainsString('name="billingAddress[birthdayMonth]"', $html);
        static::assertStringContainsString('name="billingAddress[birthdayYear]"', $html);
        static::assertStringContainsString('id="billing-birthdayDay-', $html);
        static::assertStringContainsString('id="billing-birthdayYear-', $html);
    }

    public function testBirthdaySelectCoversTheSameYearSpanAsItsPredecessor(): void
    {
        $currentYear = (int) date('Y');

        $html = $this->render('Ct:Form:BirthdaySelect', []);

        static::assertStringContainsString('<option value="' . $currentYear . '">', $html);
        static::assertStringContainsString('<option value="' . ($currentYear - 120) . '">', $html);
        static::assertStringNotContainsString('<option value="' . ($currentYear - 121) . '">', $html);
    }

    public function testRadioGroupLinksItsDescriptionToEveryRadio(): void
    {
        $html = $this->render('Ct:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'id' => 'sizeChoice',
            'label' => 'Choose a size',
            'description' => 'Sizes run small.',
            'options' => [
                ['value' => 'sm', 'label' => 'Small'],
                ['value' => 'md', 'label' => 'Medium'],
            ],
        ]);

        static::assertStringContainsString('id="sizeChoice-description"', $html);
        static::assertSame(2, substr_count($html, 'aria-describedby="sizeChoice-description sizeChoice-feedback"'));
    }

    /**
     * The fields have to work as children of a form component, so the content of the tag has to
     * reach the control instead of the wrapper.
     */
    public function testSelectRendersOwnOptionMarkupPassedAsContent(): void
    {
        $html = $this->renderTemplate(
            '<twig:Ct:Form:Select name="languageId" label="Language"><option value="en">English</option></twig:Ct:Form:Select>'
        );

        static::assertStringContainsString('<option value="en">English</option>', $html);
        static::assertMatchesRegularExpression('/<select[^>]*>\s*<option value="en">/', $html);
    }

    public function testTextareaTakesItsValueFromTheContent(): void
    {
        $html = $this->renderTemplate(
            '<twig:Ct:Form:Textarea name="content" label="Your comment">Great article</twig:Ct:Form:Textarea>'
        );

        static::assertStringContainsString('>Great article</textarea>', $html);
    }

    /**
     * A form component has to reach every field of every type, including ones a plugin adds, with
     * a single selector. The filter components use the same pattern: a specific root class plus a
     * shared role class the parent queries for.
     */
    public function testEveryFieldTypeCarriesTheSharedFieldAndControlHooks(): void
    {
        $fields = [
            'Ct:Form:Input' => ['name' => 'name'],
            'Ct:Form:Textarea' => ['name' => 'content'],
            'Ct:Form:Select' => ['name' => 'country', 'options' => [['value' => 'de', 'label' => 'Germany']]],
            'Ct:Form:Checkbox' => ['name' => 'accept'],
            'Ct:Form:RadioGroup' => ['name' => 'size', 'options' => [['value' => 'sm', 'label' => 'Small']]],
        ];

        foreach ($fields as $component => $props) {
            $html = $this->render($component, $props);

            static::assertMatchesRegularExpression('/ct-form-field(?![\w-])/', $html, $component);
            static::assertStringContainsString('ct-form-field__control ', $html, $component);
            static::assertMatchesRegularExpression('/ct-form-feedback(?![\w-])/', $html, $component);
        }
    }

    /**
     * Mapping a server violation back to its field is only possible if the field says which path it
     * answers for. Nothing else in the markup carries that.
     */
    public function testFieldExposesItsViolationPath(): void
    {
        $withPath = $this->render('Ct:Form:Input', [
            'name' => 'email',
            'violationPath' => '/email',
        ]);

        $withoutPath = $this->render('Ct:Form:Input', ['name' => 'email']);

        static::assertMatchesRegularExpression('/<div[^>]*data-violation-path="\/email"/', $withPath);
        static::assertStringNotContainsString('data-violation-path', $withoutPath);
    }

    /**
     * The group is the field, the radios are its controls, so a form component must not treat the
     * individual radios as fields with feedback of their own.
     */
    public function testRadioGroupIsOneFieldWithSeveralControls(): void
    {
        $html = $this->render('Ct:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'violationPath' => '/sizeChoice',
            'options' => [
                ['value' => 'sm', 'label' => 'Small'],
                ['value' => 'md', 'label' => 'Medium'],
            ],
        ]);

        static::assertSame(1, preg_match_all('/ct-form-field(?![\w-])/', $html));
        static::assertSame(2, substr_count($html, 'ct-form-field__control '));
        static::assertSame(1, preg_match_all('/ct-form-feedback(?![\w-])/', $html));
        static::assertSame(1, substr_count($html, 'data-violation-path="/sizeChoice"'));
        static::assertStringNotContainsString('ct-form-radio ct-form-field', $html);
    }

    /**
     * The birthday is three independent fields the server validates separately, not one field, so
     * the surrounding fieldset must not answer for a violation path of its own.
     */
    public function testBirthdaySelectIsACompositeOfThreeFields(): void
    {
        $html = $this->render('Ct:Form:BirthdaySelect', []);

        static::assertSame(3, preg_match_all('/ct-form-field(?![\w-])/', $html));
        static::assertStringNotContainsString('ct-form-birthday-select ct-form-field', $html);

        static::assertStringContainsString('data-violation-path="/birthdayDay"', $html);
        static::assertStringContainsString('data-violation-path="/birthdayMonth"', $html);
        static::assertStringContainsString('data-violation-path="/birthdayYear"', $html);
    }

    /**
     * A hidden input has nothing to label, describe or report errors for, so it drops the whole
     * field shell instead of rendering an empty one.
     */
    public function testHiddenTypeRendersNothingButTheInput(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'forwardTo',
            'type' => 'hidden',
            'value' => 'frontend.blog.detail',
            'data-captcha-token' => 'v3',
        ]);

        static::assertStringStartsWith('<input ', $html);
        static::assertStringContainsString('type="hidden"', $html);
        static::assertStringContainsString('name="forwardTo"', $html);
        static::assertStringContainsString('value="frontend.blog.detail"', $html);

        static::assertStringNotContainsString('form-group', $html);
        static::assertStringNotContainsString('<label', $html);
        static::assertStringNotContainsString('form-field-feedback', $html);
        static::assertStringNotContainsString('aria-describedby', $html);

        // It is not a field the form component should validate or map violations onto.
        static::assertStringNotContainsString('ct-form-field', $html);

        // With no wrapper to take them, plain attributes have to reach the input itself.
        static::assertMatchesRegularExpression('/<input[^>]*data-captcha-token="v3"/', $html);
    }

    public function testRendersServerSideViolationsIntoTheFeedbackElement(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'email',
            'id' => 'email',
            'label' => 'Email',
            'violationPath' => '/email',
            'formViolations' => $this->violations('/email'),
        ]);

        static::assertStringContainsString('form-control is-invalid', $html);
        static::assertStringContainsString('<div class="ct-form-feedback__message invalid-feedback">', $html);
        static::assertStringContainsString('Input should not be empty.', $html);

        // The message has to land inside the element the control points at.
        static::assertMatchesRegularExpression(
            '/id="email-feedback"[^>]*>\s*<div class="ct-form-feedback__message invalid-feedback">/',
            $html
        );
    }

    /**
     * `ConstraintViolationException::getViolations()` returns the *whole* list when the path is
     * empty, so a field marked invalid without a path would otherwise print every violation on the
     * page under itself.
     */
    public function testAFieldWithoutAViolationPathNeverPrintsAnotherFieldsViolations(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'label' => 'Name',
            'isInvalid' => true,
            'formViolations' => $this->violations('/email'),
        ]);

        static::assertStringContainsString('form-control is-invalid', $html);
        static::assertStringNotContainsString('invalid-feedback', $html);
        static::assertStringNotContainsString('Input should not be empty.', $html);
    }

    public function testAFieldOnlyPrintsTheViolationsOfItsOwnPath(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'label' => 'Name',
            'violationPath' => '/name',
            'formViolations' => $this->violations('/email'),
        ]);

        static::assertStringNotContainsString('is-invalid', $html);
        static::assertStringNotContainsString('invalid-feedback', $html);
    }

    public function testCheckboxLinksItsDescriptionToTheControl(): void
    {
        $html = $this->render('Ct:Form:Checkbox', [
            'name' => 'newsletter',
            'id' => 'newsletter',
            'label' => 'Subscribe',
            'description' => 'You can unsubscribe at any time.',
        ]);

        static::assertStringContainsString('aria-describedby="newsletter-description newsletter-feedback"', $html);
        static::assertStringContainsString('id="newsletter-description"', $html);
    }

    public function testCheckboxTakesAnAriaLabelWhenItHasNoVisibleLabel(): void
    {
        $html = $this->render('Ct:Form:Checkbox', [
            'name' => 'selectAll',
            'aria-label' => 'Select all items',
        ]);

        static::assertStringNotContainsString('<label', $html);
        static::assertStringContainsString('aria-label="Select all items"', $html);
    }

    /**
     * WCAG 2.5.3: an aria-label replaces the accessible name, so a field that already shows a label
     * must not get a second, competing one.
     */
    public function testAriaLabelIsDroppedWhenThereIsAVisibleLabel(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'label' => 'Name',
            'aria-label' => 'Something else',
        ]);

        static::assertStringContainsString('Name', $html);
        static::assertStringNotContainsString('aria-label', $html);
    }

    /**
     * `label` and `description` are the only outputs in the set that end in `|raw`. Legacy labels
     * carry links (privacy checkbox), so markup has to survive while scripts must not.
     */
    public function testLabelAndDescriptionAreSanitizedBeforeBeingRenderedRaw(): void
    {
        $html = $this->render('Ct:Form:Checkbox', [
            'name' => 'acceptedDataProtection',
            'label' => 'I accept the <a href="/privacy">privacy policy</a><script>alert(1)</script>',
            'description' => 'See our <a href="/terms">terms</a><script>alert(2)</script>',
        ]);

        static::assertStringContainsString('<a href="/privacy">privacy policy</a>', $html);
        static::assertStringContainsString('<a href="/terms">terms</a>', $html);
        static::assertStringNotContainsString('<script>', $html);
        static::assertStringNotContainsString('alert(1)', $html);
        static::assertStringNotContainsString('alert(2)', $html);
    }

    public function testSelectCanMakeThePlaceholderUnselectable(): void
    {
        $selectable = $this->render('Ct:Form:Select', [
            'name' => 'countryId',
            'placeholder' => 'Please choose',
            'options' => [['value' => 'de', 'label' => 'Germany']],
        ]);

        $locked = $this->render('Ct:Form:Select', [
            'name' => 'countryId',
            'placeholder' => 'Please choose',
            'placeholderDisabled' => true,
            'options' => [['value' => 'de', 'label' => 'Germany']],
        ]);

        static::assertStringContainsString('<option value="" selected="selected">Please choose</option>', $selectable);
        static::assertStringContainsString('<option value="" selected="selected" disabled>Please choose</option>', $locked);
    }

    /**
     * `aria-required` alone announces a field as required without anything enforcing it, because
     * the client validator reads `data-validation`. Setting `required` has to produce both.
     */
    public function testRequiredWithoutValidationRulesStillReachesClientValidation(): void
    {
        $html = $this->render('Ct:Form:Input', [
            'name' => 'name',
            'label' => 'Name',
            'required' => true,
        ]);

        static::assertStringContainsString('aria-required="true"', $html);
        static::assertStringContainsString('data-validation="required"', $html);
    }

    public function testRequiredIsPrependedToTheOtherRulesWithoutDuplicating(): void
    {
        $combined = $this->render('Ct:Form:Input', [
            'name' => 'email',
            'required' => true,
            'validationRules' => 'email',
        ]);

        $alreadyThere = $this->render('Ct:Form:Input', [
            'name' => 'email',
            'validationRules' => 'required,email',
        ]);

        static::assertStringContainsString('data-validation="required,email"', $combined);
        static::assertStringContainsString('data-validation="required,email"', $alreadyThere);
    }

    public function testRadioGroupPassesTheComputedRulesToItsRadios(): void
    {
        $html = $this->render('Ct:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'required' => true,
            'options' => [['value' => 'sm', 'label' => 'Small']],
        ]);

        static::assertStringContainsString('data-validation="required"', $html);
        static::assertStringContainsString('aria-required="true"', $html);
    }

    /**
     * After a failed submit the page re-renders with `is-invalid`, which is a colour. Without
     * `aria-invalid` a screen reader is never told the field is the one at fault.
     */
    public function testServerRenderedInvalidFieldsAreAnnouncedAsInvalid(): void
    {
        $invalid = $this->render('Ct:Form:Input', [
            'name' => 'email',
            'label' => 'Email',
            'violationPath' => '/email',
            'formViolations' => $this->violations('/email'),
        ]);

        $valid = $this->render('Ct:Form:Input', ['name' => 'email', 'label' => 'Email']);

        static::assertStringContainsString('aria-invalid="true"', $invalid);
        static::assertStringNotContainsString('aria-invalid', $valid);
    }

    public function testFormRendersItsActionMethodAndSlottedContent(): void
    {
        $html = $this->renderTemplate(
            '<twig:Ct:Form action="/some-path" method="post">'
            . '<twig:Ct:Form:Input type="text" name="something" />'
            . '<input type="hidden" name="redirectTo" value="frontend.blog.detail">'
            . '<twig:Ct:Button variant="primary" type="submit">Submit</twig:Ct:Button>'
            . '</twig:Ct:Form>'
        );

        static::assertStringStartsWith('<form ', $html);
        static::assertStringContainsString('action="/some-path"', $html);
        static::assertStringContainsString('method="post"', $html);

        static::assertStringContainsString('name="something"', $html);
        static::assertStringContainsString('<input type="hidden" name="redirectTo" value="frontend.blog.detail">', $html);
        static::assertStringContainsString('type="submit"', $html);
        static::assertStringContainsString('Submit', $html);
    }

    /**
     * Without an action the browser posts to the current URL, which is what the predecessor markup
     * relied on. An empty `action=""` would resolve differently in some browsers.
     */
    public function testFormOmitsTheActionAttributeWhenThereIsNoAction(): void
    {
        $html = $this->renderTemplate('<twig:Ct:Form>x</twig:Ct:Form>');

        static::assertStringNotContainsString('action=', $html);
        static::assertStringContainsString('method="post"', $html);
    }

    public function testFormAnnouncesItselfToTheComponentSystemWithItsDefaults(): void
    {
        $html = $this->renderTemplate('<twig:Ct:Form action="/some-path">x</twig:Ct:Form>');

        static::assertStringContainsString('data-component="Ct:Form:index"', $html);
        static::assertSame([
            'ajax' => false,
            'replaceSelectors' => [],
            'submitOnChange' => false,
            'validate' => true,
        ], $this->componentOptions($html));
    }

    /**
     * The predecessor accepted `replaceSelectors` as a bare string as well as a list, and the comment
     * templates used both spellings.
     */
    public function testFormNormalizesASingleReplaceSelectorIntoAList(): void
    {
        $html = $this->renderTemplate(
            '<twig:Ct:Form :ajax="true" replaceSelectors=".js-comment-container">x</twig:Ct:Form>'
        );

        static::assertSame(['.js-comment-container'], $this->componentOptions($html)['replaceSelectors']);
    }

    public function testFormPassesItsAjaxConfigurationToTheComponent(): void
    {
        $html = $this->renderTemplate(
            '<twig:Ct:Form :ajax="true" :replaceSelectors="[\'.js-comment-container\']"'
            . ' :submitOnChange="true" :validate="false">x</twig:Ct:Form>'
        );

        static::assertSame([
            'ajax' => true,
            'replaceSelectors' => ['.js-comment-container'],
            'submitOnChange' => true,
            'validate' => false,
        ], $this->componentOptions($html));
    }

    public function testFormKeepsItsRootClassWhileTakingArbitraryAttributes(): void
    {
        $html = $this->renderTemplate(
            '<twig:Ct:Form class="comment-form" id="comment" novalidate="novalidate" data-testid="comment">x</twig:Ct:Form>'
        );

        static::assertStringContainsString('class="ct-form comment-form"', $html);
        static::assertStringContainsString('id="comment"', $html);
        static::assertStringContainsString('novalidate="novalidate"', $html);
        static::assertStringContainsString('data-testid="comment"', $html);
    }

    /**
     * The comment submit form of `frontend/component/comment/comment-form.html.twig` expressed with
     * the components: nothing it needs may require markup or wiring outside of them.
     */
    public function testTheCommentSubmitFormIsBuildableFromTheComponents(): void
    {
        $html = $this->renderTemplate(
            '<twig:Ct:Form
                class="comment-form"
                action="/blog/1/comment"
                method="post"
                :ajax="true"
                replaceSelectors=".js-comment-container"
            >
                <twig:Ct:Form:Input type="hidden" name="forwardTo" value="frontend.blog.detail" />
                <twig:Ct:Form:Input type="hidden" name="parentId" value="2" />
                <twig:Ct:Form:Input
                    id="commentTitle"
                    name="title"
                    label="Title"
                    violationPath="/title"
                    :formViolations="formViolations"
                    validationRules="required,minLength"
                    minlength="5"
                    maxlength="255"
                />
                <twig:Ct:Form:Textarea
                    id="commentContent"
                    name="content"
                    label="Your comment"
                    violationPath="/content"
                    validationRules="required,minLength"
                    minlength="40"
                />
                <twig:Ct:Button variant="primary" type="submit">Save comment</twig:Ct:Button>
            </twig:Ct:Form>',
            ['formViolations' => $this->violations('/title')]
        );

        static::assertStringContainsString('action="/blog/1/comment"', $html);
        static::assertSame(['.js-comment-container'], $this->componentOptions($html)['replaceSelectors']);

        // The forward parameters the route needs are ordinary hidden inputs, no dedicated API.
        static::assertStringContainsString('name="forwardTo"', $html);
        static::assertStringContainsString('name="parentId"', $html);

        // Client validation reads these off the controls, the JS component reads the paths off the wrappers.
        static::assertSame(2, substr_count($html, 'data-validation="required,minLength"'));
        static::assertStringContainsString('data-violation-path="/title"', $html);
        static::assertStringContainsString('data-violation-path="/content"', $html);
        static::assertStringContainsString('minlength="40"', $html);

        // The server-rendered violation of the failed round-trip reaches the field it belongs to.
        // In production `formViolations` is a Twig global; a nested component only sees globals and
        // its own props, so here it is handed over explicitly.
        static::assertStringContainsString('aria-invalid="true"', $html);
        static::assertSame(1, substr_count($html, 'is-invalid'));
    }

    /**
     * @return array<string, mixed>
     */
    private function componentOptions(string $html): array
    {
        preg_match('/data-component-options="([^"]*)"/', $html, $matches);
        static::assertArrayHasKey(1, $matches, 'The form does not pass any options to its JavaScript component.');

        $options = json_decode(html_entity_decode($matches[1], \ENT_QUOTES), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($options);

        return $options;
    }

    private function violations(string $propertyPath): ConstraintViolationException
    {
        $violation = new ConstraintViolation(
            'Input should not be empty.',
            'VIOLATION::IS_BLANK_ERROR',
            [],
            '',
            $propertyPath,
            null,
            null,
            'VIOLATION::IS_BLANK_ERROR'
        );

        return new ConstraintViolationException(new ConstraintViolationList([$violation]), []);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(string $component, array $props): string
    {
        return $this->renderTemplate(\sprintf('{{ component(\'%s\', props) }}', $component), ['props' => $props]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context = []): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return trim($twig->createTemplate($template)->render($context));
    }
}
