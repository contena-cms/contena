<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Frontend\Controller\FormController;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class FormControllerTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;
    use FrontendControllerTestBehaviour;

    public function testHandleNewsletter(): void
    {
        $data = [
            'option' => 'subscribe',
            'email' => 'test@example.com',
            'name' => 'John',
            'lastName' => 'Doe',
        ];

        $response = $this->request(
            Request::METHOD_POST,
            '/form/newsletter',
            $this->tokenize('frontend.form.newsletter.register.handle', $data)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $type = $content[0]['type'];

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertCount(2, $content);
        static::assertSame('success', $type);
    }

    public function testHandleNewsletterFails(): void
    {
        // with incorrect email
        $data = [
            'option' => 'unsubscribe',
            'email' => 'test@example',
        ];

        $response = $this->request(
            Request::METHOD_POST,
            '/form/newsletter',
            $this->tokenize('frontend.form.newsletter.register.handle', $data)
        );
        $responseContent = $response->getContent();
        $content = json_decode((string) $responseContent, false, 512, \JSON_THROW_ON_ERROR);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame([], $content);
    }

    public function testHandleNewsletterUsesProperChannelUrl(): void
    {
        $formController = static::getContainer()->get(FormController::class);

        $request = new Request();
        $request->attributes->set('sw-sales-channel-absolute-base-url', 'wrong.test');
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'correct.test');

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('option', FormController::SUBSCRIBE);

        $formController->handleNewsletter($request, $requestDataBag, $this->createChannelContext());

        static::assertSame('correct.test', $requestDataBag->get('frontendUrl'));
    }

    public function testSendContactForm(): void
    {
        /** @var EntityRepository<SalutationCollection> $salutation */
        $salutation = static::getContainer()->get('salutation.repository');

        $salutation = $salutation->search(
            (new Criteria())->setLimit(1),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertNotNull($salutation);

        $data = [
            'salutationId' => $salutation->getId(),
            'email' => 'test@example.com',
            'name' => 'John',
            'lastName' => 'Doe',
            'subject' => 'Lorem ipsum',
            'comment' => 'Lorem ipsum dolor',
            'phone' => '+4920 3920173',
        ];

        $request = new Request();
        $request->setSession($this->getSession());
        static::getContainer()->get('request_stack')->push($request);

        $token = $this->tokenize('frontend.form.contact.send', $data);
        static::getContainer()->get('request_stack')->pop();

        $response = $this->request(
            Request::METHOD_POST,
            '/form/contact',
            $token
        );

        $responseContent = $response->getContent();
        $content = json_decode((string) $responseContent, true, 512, \JSON_THROW_ON_ERROR);
        $type = $content[0]['type'];

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $content);
        static::assertSame('success', $type);
    }

    public function testSendContactFormFails(): void
    {
        // without salutationId and with incorrect email
        $data = [
            'email' => 'test@example',
            'name' => 'John',
            'lastName' => 'Doe',
            'subject' => 'Lorem ipsum',
            'comment' => 'Lorem ipsum dolor',
            'phone' => '+4920 3920173',
        ];

        $response = $this->request(
            Request::METHOD_POST,
            '/form/contact',
            $this->tokenize('frontend.form.contact.send', $data)
        );

        $responseContent = $response->getContent();
        $content = (array) json_decode((string) $responseContent, true, 512, \JSON_THROW_ON_ERROR);
        $type = $content[0]['type'];
        $messageCount = mb_substr_count((string) $content[0]['alert'], '<li>');

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $content);
        static::assertSame('danger', $type);
        static::assertSame(2, $messageCount);
        static::assertStringContainsString('The input does not have the correct format.', (string) $content[0]['alert']);
        static::assertStringNotContainsString('VIOLATION::INVALID_FORMAT_ERROR', (string) $content[0]['alert']);
    }

    public function testSendRevocationRequest(): void
    {
        $formData = [
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'max@muster.com',
            'contractNumber' => 'SW123456789',
            'comment' => 'This is a simple comment',
        ];

        $response = $this->request(
            Request::METHOD_POST,
            '/form/revocation/request',
            $this->tokenize('frontend.form.revocation.request', $formData)
        );

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());

        $jsonResponse = $response->getContent();
        $response = (array) json_decode((string) $jsonResponse, true, 512, \JSON_THROW_ON_ERROR);
        $content = \array_shift($response);

        static::assertArrayHasKey('type', $content);
        static::assertSame('success', $content['type']);
        static::assertArrayHasKey('alert', $content);
        static::assertSame('We have received your revocation request and will process it as soon as possible.', $content['alert']);
    }

    public function testSendRevocationRequestWithInvalidData(): void
    {
        // invalid formData
        $formData = [
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'invalid email', // invalid email address
            'contractNumber' => '', // Empty contract number
            'comment' => 'This is a simple comment',
        ];

        $response = $this->request(
            Request::METHOD_POST,
            '/form/revocation/request',
            $this->tokenize('frontend.form.revocation.request', $formData)
        );

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());

        $jsonResponse = $response->getContent();
        $response = (array) json_decode((string) $jsonResponse, true, 512, \JSON_THROW_ON_ERROR);
        $content = \array_shift($response);

        static::assertArrayHasKey('type', $content);
        static::assertSame('danger', $content['type']);

        $invalidFieldCount = mb_substr_count((string) $content['alert'], '<li>');
        static::assertSame(2, $invalidFieldCount);
    }
}
