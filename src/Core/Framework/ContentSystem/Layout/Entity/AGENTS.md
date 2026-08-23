> Conceptual overview and design rationale live in the parent directory's
> [README.md](../README.md). The references and constraints below cover most code
> changes; read the README when you need the mental model.

## Source Code References

- `ContentLayoutEntity` - Layout template entity
- `ContentLayoutDefinition` - Tenant-scoped layout DAL definition; `ENTITY_NAME = 'content_layout'`, `LAYOUT_FIELD = 'layout'`, `ROOT_SOURCE_FIELD = 'root_source'`; fields: `tenant_id` (`TenantField`; null for platform layouts), `id`, `name` (string, 255), `version` (string, 20), `layout` (`ContentElementListField`, required), `root_source` (`StringField('root_source', 'rootSource')`, ApiAware + Required + Immutable — the layout's single declared root source: an entity type, a section, or `none`); blog / category / landing-page assignment associations carry `RestrictDelete`
- `ContentLayoutCollection` - Layout collection

## Quick Reference

- Repository: `content_layout.repository`
- ID generation: `Uuid::randomHex()`
- Serialization: Automatic via custom field serializers in `Field/`
- Platform layouts are not templates automatically copied or exposed to tenants. Create and edit a tenant's layouts with that tenant's `Context`; Default and Global writes remain platform-owned.
