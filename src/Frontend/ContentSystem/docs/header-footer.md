# Header and Footer Sections

The Channel API endpoints that serve the header and footer sections, the shape of an assignment record, and how one layout is picked per request.

Header and footer layouts use domain-aware resolution instead of entity-based rendering. They are independent of the main content and do not require a URL path.

**Header endpoints:**

| Endpoint                                   | Description         |
|--------------------------------------------|---------------------|
| `GET /channel-api/content-header`            | Full response       |
| `GET /channel-api/content-header-decomposed` | Decomposed response |
| `GET /channel-api/content-header-skeleton`   | Skeleton only       |
| `GET /channel-api/content-header-data`       | Data only           |

**Footer endpoints:**

| Endpoint                                   | Description         |
|--------------------------------------------|---------------------|
| `GET /channel-api/content-footer`            | Full response       |
| `GET /channel-api/content-footer-decomposed` | Decomposed response |
| `GET /channel-api/content-footer-skeleton`   | Skeleton only       |
| `GET /channel-api/content-footer-data`       | Data only           |

**Database tables:**
- `header_content_layout` - Header layout assignments
- `footer_content_layout` - Footer layout assignments

## Header/Footer Assignment Structure

```json
{
  "id": "<uuid>",
  "domainId": "<domain-uuid>|null",
  "channelId": "<channel-uuid>|null",
  "contentLayoutId": "<layout-uuid>"
}
```

Fields:
- `domainId` - Channel domain scope (`null` = not domain-specific)
- `channelId` - Channel scope (`null` = global)
- `contentLayoutId` - Layout to use

## Domain-Aware Resolution

Resolution priority (three-tier fallback): **domain + channel** > **channel only** > **global** (both null).

Example: A site with domains `example.com` and `example.cn` can have different headers per domain, with a fallback header for the entire channel, and a global fallback for all channels.

## Header/Footer Placeholders

Header and footer layouts do not have entity-based placeholders. Query parameters passed to the endpoint become available as placeholders.

```
/channel-api/content-header?activeCategoryId=abc123
```

Makes `{{activeCategoryId}}` available in the header layout.

Header and footer sections do not support partial rendering (`elementId` parameter).
