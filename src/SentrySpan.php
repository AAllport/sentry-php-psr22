<?php

namespace AAllport\SentryPsrTracing;

use Psr\Tracing\SpanInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Stringable;
use Throwable;
use Traversable;

class SentrySpan implements SpanInterface
{
    private SpanContext $spanContext;
    private ?Span $parent;
    private Span $span;

    public function __construct(string $spanName)
    {
        $this->spanContext = new SpanContext();
        $this->spanContext->setOp($spanName);
    }

    public function setAttributes(iterable $attributes): SpanInterface
    {
        $this->spanContext->setTags(
            $attributes instanceof Traversable
                ? iterator_to_array($attributes)
                : (array)$attributes
        );

        return $this;
    }

    public function setAttribute(string $key, float|bool|int|string|Stringable $value): SpanInterface
    {
        $this->spanContext->setTags([$key => $value]);
        return $this;
    }

    public function setStatus(int $status, ?string $description): SpanInterface
    {
        if ($status < self::STATUS_ERROR) {
            $this->span->setStatus(null);
        } elseif ($status < self::STATUS_OK) {
            $this->span->setStatus(SpanStatus::unknownError());
        } else {
            $this->span->setStatus(SpanStatus::ok());
        }

        return $this;
    }

    public function addException(Throwable $t): SpanInterface
    {
        SentrySdk::getCurrentHub()->captureException($t);
        return $this;
    }

    public function start(): SpanInterface
    {
        $parent = SentrySdk::getCurrentHub()->getSpan();
        $this->span = $parent->startChild($this->spanContext);

        return $this;
    }

    public function activate(): SpanInterface
    {
        $hub = SentrySdk::getCurrentHub();

        $this->parent = $hub->getSpan() ?? $hub->getTransaction();
        $hub->setSpan($this->span);

        return $this;
    }


    public function finish(): void
    {
        $hub = SentrySdk::getCurrentHub();

        $this->span->finish();
        $hub->setSpan($this->parent);
    }

    public function toTraceContextHeaders(): array
    {
        return [
            'sentry-trace' => $this->span->toTraceparent(),
            'baggage' => $this->span->toBaggage(),
        ];
    }


}