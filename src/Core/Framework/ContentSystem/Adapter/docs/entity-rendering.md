# Entity-Based Rendering

The main content section: the Channel API endpoints that render an entity, the assignment record that binds a layout to it, and the channel fallback that picks between assignments.

Blogs, Categories, and Landing Pages can render directly using ContentSystem layouts. This is the primary method for rendering entity-based pages.

**Endpoints:**

| Endpoint                                   | Description         |
|--------------------------------------------|---------------------|
| `GET /channel-api/content/{path}`            | Full response       |
| `GET /channel-api/content-decomposed/{path}` | Decomposed response |
| `GET /channel-api/content-skeleton/{path}`   | Skeleton only       |
| `GET /channel-api/content-data/{path}`       | Data only           |

**Supported path patterns:**
- `blog/{blogId}` - Blog detail pages
- `category/{categoryId}` - Category pages
- `landing-page/{landingPageId}` - Landing pages

**Example requests:**
- `/channel-api/content/blog/abc123def456` - Renders blog with ID abc123def456
- `/channel-api/content/category/xyz789abc012` - Renders category with ID xyz789abc012
- `/channel-api/content/landing-page/ghi345jkl678` - Renders landing page with ID ghi345jkl678
- `/channel-api/content/blog/abc123def456?elementId=blog-images` - Renders only the `blog-images` element subtree
- `/channel-api/content-decomposed/blog/abc123def456?elementId=blog-images` - Same, decomposed format

**Database tables:**
- `blog_content_layout` - Blog layout assignments
- `category_content_layout` - Category layout assignments
- `landing_page_content_layout` - Landing page layout assignments

## Assignment Structure

```json
{
  "id": "<uuid>",
  "blogId": "<blog-uuid>",
  "channelId": "<channel-uuid>|null",
  "contentLayoutId": "<layout-uuid>"
}
```

Fields:
- Entity ID (`blogId`/`categoryId`/`landingPageId`) - Entity to render
- `channelId` - Channel scope (`null` = global)
- `contentLayoutId` - Layout to use

## Channel Resolution

Resolution priority: **channel specific** > **global** (null `channelId`).

Example: Blog with global layout and B2B-specific layout. B2B channel uses specific assignment, all other channels use global.
