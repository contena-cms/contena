---
title: Creating events in Contena
date: 2020-11-06
area: core
tags: [event, context, sales-channel-context]
--- 

## Context

Events throughout Contena are quite inconsistent.
It is not defined which data it must or can contain.
This mainly depends on the domain where the events are thrown.

## Decision

Developers should always have access to the right context of the current request,
at least the `Contena\Core\Framework\Context` should be present as property in events.
If the event is thrown in a SalesChannel context,
the `Contena\Core\System\SalesChannel\SalesChannelContext` should also be present as property.

## Consequences

From now on every new event must implement the `Contena\Core\Framework\Event\ContenaEvent` interface.
If a `Contena\Core\System\SalesChannel\SalesChannelContext` is also available,
the `Contena\Core\Framework\Event\ContenaSalesChannelEvent` interface must be implemented instead.
