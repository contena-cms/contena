<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod;

/**
 * Platform-wide payment method vocabulary.
 *
 * Method codes describe the payment *form*, not the channel implementation:
 * PAGE is any web page redirect checkout (alipay.trade.page.pay, a paypal
 * web checkout, ...), H5 any mobile browser checkout, and so on. Multiple
 * channels may provide the same method; a channel maps the method code onto
 * its own APIs in its gateway actions.
 *
 * A channel that needs a form no existing code covers declares a new code in
 * the `payment_channel_method` data model; the platform treats it as a plain
 * method code without changing this vocabulary.
 */
final class PaymentMethods
{
    final public const string H5 = 'h5';
    final public const string APP = 'app';
    final public const string MINI_PROGRAM = 'mini_program';
    final public const string PAGE = 'page';
    final public const string FACE = 'face';
    final public const string JSAPI = 'jsapi';
    final public const string NATIVE = 'native';
    final public const string AUTO_DEBIT = 'auto_debit';
}
