<?php

namespace AAllport\SentryPsrTracingTests;


use AAllport\SentryPsrTracing\SentryTracer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

class SentryTracerTest extends TestCase
{
    protected function createTransaction(): Transaction
    {
        $transactionContext = new TransactionContext();
        $transactionContext->setName('Test Transaction');
        $transactionContext->setOp('http.request');

        $hub = SentrySdk::getCurrentHub();
        $transaction = $hub->startTransaction($transactionContext);
        $hub->setSpan($transaction);

        return $transaction;
    }

    public function testCanCreateSpan()
    {
        $hub = SentrySdk::getCurrentHub();
        $transaction = $this->createTransaction();

        $tracer = new SentryTracer();
        $span = $tracer->createSpan("fooSpan");
        $span->start();

        /** @var Span $sentrySpan */
        $sentrySpan = $this->peak($span, 'span');
        $this->assertSame('fooSpan', $sentrySpan->getOp());
    }

    public function testChildImplicitPropagation()
    {
        $hub = SentrySdk::getCurrentHub();
        $transaction = $this->createTransaction();

        $tracer = new SentryTracer();

        $span = $tracer->createSpan("fooSpan")->activate();
        $spanVendor = $this->peak($span, 'span');

        $childSpan = $tracer->createSpan("barSpan")->activate();
        $childSpanParentVendor = $this->peak($childSpan->getParent(), 'span');

        $this->assertSame($spanVendor, $childSpanParentVendor);
    }

    public function testChildExplicitPropagation()
    {
        $hub = SentrySdk::getCurrentHub();
        $transaction = $this->createTransaction();

        $tracer = new SentryTracer();
        $span = $tracer->createSpan("parent")->activate();

        $siblingOne = $span->createChild('siblingOne')->activate();
        $siblingTwo = $span->createChild('siblingTwo')->activate();

        $this->assertCount(2, $span->getChildren());

        $this->assertSame($siblingOne->getParent(), $span);
        $this->assertSame($siblingTwo->getParent(), $span);
    }

    public function peak(object $class, string $property): mixed
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $reflector = new ReflectionProperty($class, $property);
        $reflector->setAccessible(true);
        return $reflector->getValue($class);
    }
}
