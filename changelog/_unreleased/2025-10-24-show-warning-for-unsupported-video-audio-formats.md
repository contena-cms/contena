---
title: Add browser compatibility warning for unsupported media formats
issue: 13044
author: Dumea Alexandru
author_email: a.dumea@contena.cn
author_github: @Dumea Alexandru
---
# Administration
* Added warning icon and banner for video and audio formats that may not be supported in all browsers (e.g., MOV, AVI, WMV)
* Added `media-format.service.js` to centralize playable format detection and provide reusable helper functions
* Changed `ct-media-preview-v2` component to display warning icon for unsupported video/audio formats
* Changed `ct-media-quickinfo` component to display warning banner in sidebar for unsupported video/audio formats
