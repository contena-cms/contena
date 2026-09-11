# Agent User Stories — Platform Primitives

User stories for what an AI agent can accomplish using core Contena MCP tools (`contena-*`). These examples map Shopware's generic DAL/MCP capability to Contena content entities and deliberately exclude commerce workflows.

**Out of scope here**: Developer tasks such as code generation, testing, linting, cache clearing, and deployment.

**Status legend:** COVERED = fully working, PARTIAL = possible but limited, GAP = not yet possible.

## Category 1: Data Exploration

- **US-1** [COVERED]: "Which blogs were updated most recently?"
  - Tools: `contena-entity-search` on `blog` with a descending `updatedAt` sort.
- **US-2** [COVERED]: "Show me the schema of the member entity."
  - Tools: `contena-entity-schema`.
- **US-3** [COVERED]: "How many published blogs are in each category?"
  - Tools: `contena-entity-aggregate` on `blog`.

## Category 2: Configuration

- **US-4** [COVERED]: "What are the current listing settings and how do I change the default sorting?"
  - Tools: `contena-system-config-read` and `contena-system-config-write` with `dryRun`.
- **US-5** [COVERED]: "Read the configuration for the Web channel."
  - Tools: `contena-system-config-read` with the channel ID.

## Category 3: Flow / Automation Discovery

- **US-6** [PARTIAL]: "Which event and action can publish a notification when a blog changes?"
  - Resources: `contena://business-events` and `contena://flow-actions`.
  - Gap: the resources support discovery; creating a complete flow still uses generic entity writes and requires knowledge of the flow schema.
- **US-7** [PARTIAL]: "What automations are currently configured?"
  - Tools: `contena-entity-search` on `flow` with its `sequences` association.

## Category 4: Media Management

- **US-8** [COVERED]: "Upload an image or document from a URL."
  - Tools: `contena-media-upload`.
- **US-9** [COVERED]: "Attach an uploaded image to a blog."
  - Tools: `contena-media-upload`, followed by `contena-entity-upsert` on `blog`.

## Category 5: Theme / Appearance

- **US-10** [COVERED]: "Change the primary brand color of the Web channel to blue."
  - Tools: `contena-theme-config` with `action: "update"` and `{"ct-color-brand-primary":{"value":"#0000ff"}}`.
- **US-11** [COVERED]: "Update the channel logo in the theme."
  - Tools: `contena-media-upload`, followed by `contena-theme-config` using the returned media ID.

## Category 6: Content Management

- **US-12** [COVERED]: "Create a draft post."
  - Tools: `contena-entity-upsert` on `blog` with `type: "post"` and `active: false`.
- **US-13** [COVERED]: "Assign a blog to a category."
  - Tools: `contena-entity-upsert` on `blog` with the `categories` association.
- **US-14** [COVERED]: "Find landing pages available to a channel."
  - Tools: `contena-entity-search` on `landing_page` with channel associations.

## Category 7: Member and Channel Context

- **US-15** [COVERED]: "List active members in a member group."
  - Tools: `contena-entity-search` on `member`.
- **US-16** [COVERED]: "Which languages and domains are configured for each channel?"
  - Resources: `contena://channels` and `contena://languages`.
- **US-17** [COVERED]: "Show the current public Channel API session context."
  - Tools: `contena-channel-api-context` on `/channel-api/_mcp`.

## Postponed Improvements

- A dedicated flow authoring tool remains postponed until event/action validation and multi-action flow support can be implemented together.
