<?php

namespace AAllport\SentryPsrTracing;

use Psr\Tracing\SpanInterface;
use Psr\Tracing\TracerInterface;
use Sentry\SentrySdk;

class SentryTracer implements TracerInterface
{
    public function createSpan(string $spanName): SpanInterface
    {
        return new SentrySpan($spanName);
    }

    public function getCurrentTraceId(): string
    {
        $parent = SentrySdk::getCurrentHub()->getSpan();
        return $parent->getTraceId();
    }

    public function getRootSpan(): SpanInterface
    {
        $hub = SentrySdk::getCurrentHub();
        return SentrySpan::fromVendor($hub->getTransaction());
    }

    public function getCurrentSpan(): SpanInterface
    {
        $hub = SentrySdk::getCurrentHub();
        return SentrySpan::fromVendor($hub->getSpan() ?? $hub->getTransaction());
    }
}
