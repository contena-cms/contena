<?php declare(strict_types=1);

return [
    'checkout.user.before.login' => <<<'EOD'
Triggers as soon as a user logs in
EOD,
    'checkout.user.changed-payment-method' => <<<'EOD'
Triggers when a user changes his payment method in the checkout process
EOD,
    'checkout.user.deleted' => <<<'EOD'
Triggers if a user gets deleted
EOD,
    'checkout.user.double_opt_in_guest_order' => <<<'EOD'
Triggers as soon as double opt-in is accepted in a guest order
EOD,
    'checkout.user.double_opt_in_registration' => <<<'EOD'
Triggers when a user commits to his registration via double opt in
EOD,
    'checkout.user.guest_register' => <<<'EOD'
__EMPTY__
EOD,
    'checkout.user.login' => <<<'EOD'
Triggers as soon as a user logs in
EOD,
    'checkout.user.logout' => <<<'EOD'
Triggers when a user logs out
EOD,
    'checkout.user.register' => <<<'EOD'
Triggers when a new user was registered
EOD,
    'checkout.order.placed' => <<<'EOD'
Triggers when an order is placed
EOD,
    'contact_form.send' => <<<'EOD'
Triggers when a contact form is send
EOD,
    'user.group.registration.accepted' => <<<'EOD'
__EMPTY__
EOD,
    'user.group.registration.declined' => <<<'EOD'
__EMPTY__
EOD,
    'user.recovery.request' => <<<'EOD'
Triggers when a user recovers his password
EOD,
    'document.generation.completed' => <<<'EOD'
Triggers when a document is generated or uploaded for an order
EOD,
    'document.generation.deleted' => <<<'EOD'
Triggers when a document is deleted
EOD,
    'mail.after.create.message' => <<<'EOD'
__EMPTY__
EOD,
    'mail.before.send' => <<<'EOD'
Triggers before a mail is send
EOD,
    'mail.sent' => <<<'EOD'
Triggers when a mail is send from Contena
EOD,
    'newsletter.confirm' => <<<'EOD'
__EMPTY__
EOD,
    'newsletter.register' => <<<'EOD'
__EMPTY__
EOD,
    'newsletter.unsubscribe' => <<<'EOD'
__EMPTY__
EOD,
    'newsletter.update' => <<<'EOD'
__EMPTY__
EOD,
    'blog_export.log' => <<<'EOD'
__EMPTY__
EOD,
    'review_form.send' => <<<'EOD'
Triggers when a blog review form is send
EOD,
    'state_enter.order.state.cancelled' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order.state.completed' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order.state.in_progress' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order.state.open' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_delivery.state.cancelled' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_delivery.state.open' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_delivery.state.returned' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_delivery.state.returned_partially' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_delivery.state.shipped' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_delivery.state.shipped_partially' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.authorized' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.cancelled' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.chargeback' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.failed' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.in_progress' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.open' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.paid' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.paid_partially' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.refunded' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.refunded_partially' => <<<'EOD'
__EMPTY__
EOD,
    'state_enter.order_transaction.state.reminded' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order.state.cancelled' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order.state.completed' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order.state.in_progress' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order.state.open' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_delivery.state.cancelled' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_delivery.state.open' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_delivery.state.returned' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_delivery.state.returned_partially' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_delivery.state.shipped' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_delivery.state.shipped_partially' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.authorized' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.cancelled' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.chargeback' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.failed' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.in_progress' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.open' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.paid' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.paid_partially' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.refunded' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.refunded_partially' => <<<'EOD'
__EMPTY__
EOD,
    'state_leave.order_transaction.state.reminded' => <<<'EOD'
__EMPTY__
EOD,
    'admin_user.recovery.request' => <<<'EOD'
__EMPTY__
EOD,
    'blog.written' => <<<'EOD'
__EMPTY__
EOD,
    'blog.deleted' => <<<'EOD'
__EMPTY__
EOD,
    'blog_price.written' => <<<'EOD'
__EMPTY__
EOD,
    'blog_price.deleted' => <<<'EOD'
__EMPTY__
EOD,
    'category.written' => <<<'EOD'
__EMPTY__
EOD,
    'category.deleted' => <<<'EOD'
__EMPTY__
EOD,
    'channel.written' => <<<'EOD'
__EMPTY__
EOD,
    'channel.deleted' => <<<'EOD'
__EMPTY__
EOD,
    'user.written' => <<<'EOD'
__EMPTY__
EOD,
    'user.deleted' => <<<'EOD'
__EMPTY__
EOD,
    'user_address.written' => <<<'EOD'
__EMPTY__
EOD,
    'user_address.deleted' => <<<'EOD'
__EMPTY__
EOD,
    'blog' => <<<'EOD'
__EMPTY__
EOD,
    'blog_price' => <<<'EOD'
__EMPTY__
EOD,
    'category' => <<<'EOD'
__EMPTY__
EOD,
    'channel' => <<<'EOD'
__EMPTY__
EOD,
    'user' => <<<'EOD'
__EMPTY__
EOD,
    'user_address' => <<<'EOD'
__EMPTY__
EOD,
];
