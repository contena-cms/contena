---
title: Implement mt-banner code mod
issue: NEXT-34278
flag: V6_7_0_0
author: Sebastian Seggewiss
author_email: s.seggewiss@contena.cn
author_github: @seggewiss
---
# Administration
* Added wrapper component for ct-alert
* Added codemods (ESLint rules) for converting ct-alert to mt-banner
___
# Next Major Version Changes
## Removal of "ct-alert" & "ct-alert-deprecated":
The old "ct-alert" component will be removed in the next major version. Please use the new "mt-banner" component instead.

We provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-banner" component.

If you don't want to use the codemod, you can manually replace all occurrences of "ct-alert" with "mt-banner".

Following changes are necessary:

### "ct-alert" is removed
Replace all component names from "ct-alert" with "mt-banner"

Before:
```html
<ct-alert />
```
After:
```html
<mt-banner />
```

### Variants warning, critical and success must be replaced
Before:
```html
<ct-alert variant="success" />
<ct-alert variant="warning" />
<ct-alert variant="error" />
```

After:
```html
<ct-alert variant="positive" />
<ct-alert variant="attention" />
<ct-alert variant="critical" />
```

### Property appearance was removed
Before:
```html
<ct-alert appearence="..." />
```

After:
- Custom styling will be necessary

### Property showIcon got replaced by hideIcon
Before:
```html
<ct-alert :show-icon="condition" />
```

After:
```html
<ct-alert :hide-icon="!condition" />
```

### Slot actions got removed
Before:
```html
<ct-alert>
    <template #actions>
        ...
    </template>
</ct-alert>
```

After:
- Incorporate your actions elsewhere
