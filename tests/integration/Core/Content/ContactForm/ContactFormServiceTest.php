<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ContactForm;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ContactForm\Channel\ContactFormRoute;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ContactFormServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;

    private ContactFormRoute $contactFormRoute;

    protected function setUp(): void
    {
        $this->contactFormRoute = static::getContainer()->get(ContactFormRoute::class);
    }

    public function testContactFormSendMail(): void
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $context = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $validationEventDidRun = false;
        $validationListenerClosure = static function () use (&$validationEventDidRun): void {
            $validationEventDidRun = true;
        };

        $validationEventName = 'framework.validation.contact_form.create';

        EventHookDispatcher::fromContainer(static::getContainer())->on($validationEventName, $validationListenerClosure);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', true);
        $systemConfig->set('core.basicInformation.email', 'doNotReply@example.com');

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'email' => 'test@contena.cn',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertTrue($validationEventDidRun, "The $validationEventName Event did not run");
        static::assertInstanceOf(MailSentEvent::class, $mail);
        $html = $mail->getContents()['text/html'];
        static::assertIsString($html);
        static::assertStringContainsString('Contact email address: test@contena.cn', $html);
        static::assertStringContainsString('Lorem ipsum dolor sit amet', $html);
    }

    public function testContactFormFirstNameRequiredException(): void
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $context = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => '',
            'lastName' => 'Lastname',
            'email' => 'test@contena.cn',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
    }

    public function testContactFormLastNameRequiredException(): void
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $context = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Firstname',
            'lastName' => '',
            'email' => 'test@contena.cn',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
    }

    public function testContactFormPhoneNumberRequiredException(): void
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $context = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', true);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'email' => 'test@contena.cn',
            'phone' => '',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
    }

    public function testContactFormOptionalFieldsSendMail(): void
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $context = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => '',
            'lastName' => '',
            'email' => 'test@contena.cn',
            'phone' => '',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail);
        $html = $mail->getContents()['text/html'];
        static::assertIsString($html);
        static::assertStringContainsString('Contact email address: test@contena.cn', $html);
        static::assertStringContainsString('Lorem ipsum dolor sit amet', $html);
    }
}
