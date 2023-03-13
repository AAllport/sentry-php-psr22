<?php

namespace AAllport\SentryPsrTracing;

use Psr\Tracing\SpanInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\Span as VendorSpan;
use Sentry\Tracing\SpanContext as VendorSpanContext;
use Sentry\Tracing\SpanStatus as VendorSpanStatus;
use Stringable;
use Throwable;
use Traversable;

class SentrySpan implements SpanInterface
{
    private VendorSpanContext $spanContext;
    private ?VendorSpan $span = null;

    private ?self $parent;
    /** @var array<self> */
    private array $children = [];


    public function __construct(string $spanName)
    {
        $this->spanContext = new VendorSpanContext();
        $this->spanContext->setOp($spanName);
    }

    public static function fromVendor(VendorSpan $vendorSpan): self
    {
        $span = new self($vendorSpan->getOp());
        $span->span = $vendorSpan;
        return $span;
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

    public function getAttributes(): iterable
    {
        return $this->spanContext->getTags();
    }

    public function setAttribute(string $key, null|float|bool|int|string|Stringable $value): SpanInterface
    {
        $this->spanContext->setTags([$key => $value]);
        return $this;
    }

    public function getAttribute(string $key): null|string|int|float|bool|Stringable
    {
        return $this->spanContext->getTags()[$key];
    }

    public function setStatus(int $status, ?string $description): SpanInterface
    {
        if ($status < self::STATUS_ERROR) {
            $this->span->setStatus(null);
        } elseif ($status < self::STATUS_OK) {
            $this->span->setStatus(VendorSpanStatus::unknownError());
        } else {
            $this->span->setStatus(VendorSpanStatus::ok());
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
        $hub = SentrySdk::getCurrentHub();

        $this->parent ??= self::fromVendor($hub->getSpan() ?? $hub->getTransaction());
        $this->span = $this->parent->span->startChild($this->spanContext);

        return $this;
    }

    public function activate(): SpanInterface
    {
        $hub = SentrySdk::getCurrentHub();

        if (!$this->span) {
            $this->start();
        }
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


    public function createChild(string $spanName): SpanInterface
    {
        $child = new SentrySpan($spanName);
        $child->parent = $this;
        $this->children[]=$child;
        return $child;
    }

    public function getParent(): SpanInterface|null
    {
        return $this->parent;
    }

    public function getChildren(): array
    {
        return $this->children;
    }
}
