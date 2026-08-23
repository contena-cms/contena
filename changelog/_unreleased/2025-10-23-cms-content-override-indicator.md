---
title: CMS content override indicator
issue: 12131
author: Benedikt Schulze Baek
author_email: b.schulze-baek@contena.cn
author_github: @bschulzebaek
---
# Administration
* Deprecated `extractSlotOverrides`, `getCmsPageOverrides` and `deleteSpecificKeys` in the `ct-categeory-detail` component. Their behavior will be handled by the new component `ct-cms-form-sync`.
* Deprecated `getCmsPageOverrides` and `deleteSpecificKeys` in the `ct-product-detail` component. Their behavior will be handled by the new component `ct-cms-form-sync`.
* Added the new component `ct-cms-inherit-wrapper` to indicate and control inherited layout content on content pages. It emits the two events `inheritance:remove` and `inheritance:restore` for individual slot config fields.
