---
title: Implement save media modal
author: Quynh Nguyen
author_email: q.nguyen@contena.cn
author_github: @quynhnguyen68
---
# Administration
* Added component `ct-media-save-modal` in module `ct-media`
    * This component can be open by `ui.mediaModal.openSaveMedia` command of meteor admin SDK
    * After select a folder, a new media entity is created with selected folderId and passing to callback function these parameters (file name, folderId, mediaId). User save new media with provided mediaId from callback function and upload media Admin API.
* Changed in `src/app/component/structure/ct-media-modal-renderer/index.ts`
    * Added method `onSaveMedia`
    * Added method `closeSaveModal`
    * Added method `saveMediaModal`
* Added component `ct-media-save-modal` in `src/app/component/structure/ct-media-modal-renderer/ct-media-modal-renderer.html.twig`
* Added props `allowCreateFolder` and `disabled` in `src/module/ct-media/component/ct-media-library/index.js`.
* Changed in `src/app/component/media/ct-media-base-item/index.js`
    * Added props `disabled`
    * Changed method `handleItemClick` to prevent clicking if `disabled` is true.
* Changed in `src/module/ct-media/component/ct-media-breadcrumbs/index.js`
    * Added props `disabled`
    * Changed method `onBreadcrumbsItemClicked` to prevent clicking if `disabled` is true.
* Changed in `src/module/ct-media/component/ct-media-library/index.js`
    * Added props `disabled`
    * Added props `allowCreateFolder` to show `Add new folder` button

