---
title: Simplify emitting metrics
issue: NEXT-37689
flag: TELEMETRY_METRICS
---

# Core
* Removed `Contena\Core\Framework\Telemetry\Metrics\MetricEventDispatcher`
* Removed the ability to configure Metrics via Attributes
* Added `Contena\Core\Framework\Telemetry\Metrics\Config\MetricConfiguration` concept to configure metrics
* Added `Contena\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface` that enables configuring metric transports via `Contena\Core\Framework\Telemetry\Metrics\Config\TransportConfig`
* Changed Metric types with ` Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric` to simplify the emitting process
* Changed `Contena\Core\Framework\Telemetry\Metrics\Metric\MetricInterface` with `Contena\Core\Framework\Telemetry\Metrics\Metric`
* Added the ability to configure labels on metrics
* Added the ability to disable and enable metrics individually
