<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderUser\OrderUserEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\System\User\Service\GuestAuthenticator;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(GuestAuthenticator::class)]
class GuestAuthenticatorTest extends TestCase
{
    #[DataProvider('provideRequestData')]
    public function testGuestAuthentication(Request $request, ?\Exception $expectedException): void
    {
        $order = new OrderEntity();
        $orderUser = new OrderUserEntity();
        $user = new UserEntity();
        $user->setGuest(true);
        $orderUser->setUser($user);
        $orderUser->setEmail('test@example.com');
        $order->setOrderUser($orderUser);
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setZipcode('12345');
        $order->setBillingAddress($billingAddress);

        if ($expectedException !== null) {
            $this->expectExceptionObject($expectedException);
        }
        (new GuestAuthenticator())->validate($order, $request);
        if ($expectedException === null) {
            $this->expectNotToPerformAssertions();
        }
    }

    public function testGuestAuthenticationWithRegisteredUser(): void
    {
        $order = new OrderEntity();
        $orderUser = new OrderUserEntity();
        $user = new UserEntity();
        $user->setGuest(false);
        $orderUser->setUser($user);
        $order->setOrderUser($orderUser);
        $request = new Request();

        $this->expectExceptionObject(UserException::userNotLoggedIn());
        (new GuestAuthenticator())->validate($order, $request);
    }

    #[DataProvider('provideZipcodeWhitespaceData')]
    public function testGuestAuthenticationKeepsZipcodeWhitespaceSignificant(string $storedZipcode, string $requestZipcode, ?\Exception $expectedException): void
    {
        $order = $this->createGuestOrder($storedZipcode);
        $request = new Request([
            'email' => 'test@example.com',
            'zipcode' => $requestZipcode,
        ]);

        if ($expectedException !== null) {
            $this->expectExceptionObject($expectedException);
        }

        (new GuestAuthenticator())->validate($order, $request);

        static::assertNull($expectedException);
    }

    /**
     * @return array<string, array{0: Request, 1: \Exception|null}>
     */
    public static function provideRequestData(): array
    {
        return [
            'valid data in query' => [new Request([
                'email' => 'test@example.com',
                'zipcode' => '12345',
            ]), null],
            'valid data in request' => [new Request([], [
                'email' => 'test@example.com',
                'zipcode' => '12345',
            ]), null],
            'invalid email in query' => [new Request([
                'email' => 'foo@bar.com',
                'zipcode' => '12345',
            ]), UserException::wrongGuestCredentials()],
            'invalid email in request' => [new Request([], [
                'email' => 'foo@bar.com',
                'zipcode' => '12345',
            ]), UserException::wrongGuestCredentials()],
            'invalid zipcode in query' => [new Request([
                'email' => 'test@example.com',
                'zipcode' => 'abc',
            ]), UserException::wrongGuestCredentials()],
            'invalid zipcode in request' => [new Request([], [
                'email' => 'test@example.com',
                'zipcode' => 'abc',
            ]), UserException::wrongGuestCredentials()],
            'missing zipcode in query' => [new Request([
                'email' => 'test@example.com',
            ]), UserException::guestNotAuthenticated()],
            'missing zipcode in request' => [new Request([], [
                'email' => 'test@example.com',
            ]), UserException::guestNotAuthenticated()],
            'missing email in query' => [new Request([
                'zip' => '12345',
            ]), UserException::guestNotAuthenticated()],
            'missing email in request' => [new Request([], [
                'zip' => '12345',
            ]), UserException::guestNotAuthenticated()],
            'no data' => [new Request(), UserException::guestNotAuthenticated()],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: \Exception|null}>
     */
    public static function provideZipcodeWhitespaceData(): array
    {
        return [
            'matching surrounding spaces' => [' 12345 ', ' 12345 ', null],
            'matching tab and newline' => ["\t12345\n", "\t12345\n", null],
            'trimmed input does not match stored whitespace' => ["\t12345\n", '12345', UserException::wrongGuestCredentials()],
            'different surrounding whitespace does not match' => [' 12345 ', "\t12345\n", UserException::wrongGuestCredentials()],
        ];
    }

    private function createGuestOrder(string $zipcode): OrderEntity
    {
        $order = new OrderEntity();
        $orderUser = new OrderUserEntity();
        $user = new UserEntity();
        $user->setGuest(true);
        $orderUser->setUser($user);
        $orderUser->setEmail('test@example.com');
        $order->setOrderUser($orderUser);
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setZipcode($zipcode);
        $order->setBillingAddress($billingAddress);

        return $order;
    }
}
