# Notifications

Notifications are used to inform users of important events, such as errors, success messages or of a completed action. 

### Use notification mixin

To create notifications, you can use the `notification` mixin. The mixin provides various methods for creating notifications:
 * `createNotificationSuccess`
 * `createNotificationInfo`
 * `createNotificationWarning`
 * `createNotificationError`

```javascript
Contena.Component.register('my-component', {
    mixins: [
        Mixin.getByName('notification')
    ],

    methods: {
        success: function () {
            this.createNotificationSuccess({ title: 'The action was completed' })
        }
    }
});
```

You can also add actions to the notifications. Actions are buttons that allow users to take specific actions directly from the notification, such as navigation to a route or running a function:

```javascript
Contena.Component.register('my-component', {
    mixins: [
        Mixin.getByName('notification')
    ],

    methods: {
        success: function () {
            this.createNotificationSuccess({ 
                title: 'The action was completed',
                actions: [
                    {
                        label: 'Go to products',
                        route: { name: 'ct.product.index' },
                    },
                    {
                        label: 'Do something else',
                        method: () => console.log('completed'),
                    }
                ],
            })
        }
    }
});
```

### Notification transformers

Notification transformers allow you to enhance backend notifications with actions and translations. This is particularly useful for creating interactive notifications that guide users to take specific actions.

You can register a transformer using the `registerTransformer` method on the `notification` store. Use the notification message value as the key for the transformer. The transformer function receives the notification object as its first argument and must return a new notification object.

The following shows how to register a transformer for the `notification.permissions.requested` notification:

```typescript
Contena.Store.get('notification').registerTransformer(
    'notification.permissions.requested',
    (notification: NotificationType): NotificationType => {
        const root = Contena.Application.getApplicationRoot() as App<Element>;

        return {
            ...notification,
            variant: 'warning' as NotificationVariant,
            title: root.$tc('ct-extension.notifications.reviewPermissionRequests.title'),
            message: root.$tc('ct-extension.notifications.reviewPermissionRequests.message'),
            actions: [
                {
                    label: root.$tc('ct-extension.notifications.reviewPermissionRequests.action'),
                    route: { name: 'ct.extension.my-extensions.listing' },
                },
            ],
        };
    },
);
```
