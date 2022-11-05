<?php

namespace AAllport\SentryPsrTracingTests;


use AAllport\SentryPsrTracing\SentryTracer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\TransactionContext;

class SentryTracerTest extends TestCase
{
    public function testCanCreateSpan()
    {
        $transactionContext = new TransactionContext();
        $transactionContext->setName('Test Transaction');
        $transactionContext->setOp('http.request');

        $hub = SentrySdk::getCurrentHub();
        $transaction = $hub->startTransaction($transactionContext);
        $hub->setSpan($transaction);

        $tracer = new SentryTracer();
        $span = $tracer->createSpan("fooSpan");
        $span->start();

        /** @var Span $sentrySpan */
        $sentrySpan = $this->peak($span, 'span');
        $this->assertSame('fooSpan', $sentrySpan->getOp());
    }

    public function peak(object $class, string $property): mixed
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $reflector = new ReflectionProperty($class, $property);
        return $reflector->getValue($class);
    }
}