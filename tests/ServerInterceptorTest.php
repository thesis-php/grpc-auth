<?php

declare(strict_types=1);

namespace Thesis\Grpc\Auth;

use Amp\Cancellation;
use Amp\NullCancellation;
use Google\Rpc\Code;
use Testo\Assert;
use Testo\Test;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\RpcType;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\ServerStream;

#[Test]
final class ServerInterceptorTest
{
    public function passesAuthenticatedUnaryCallsToTheHandler(): void
    {
        $auth = StaticAuth::bearer('t');
        $md = $auth->apply(new Metadata(), new NullCancellation());
        $response = new \stdClass();

        $result = new ServerInterceptor($auth)->interceptUnary(
            new \stdClass(),
            new StreamInfo('/svc/Method', RpcType::Unary),
            $md,
            new NullCancellation(),
            static fn(object $request, StreamInfo $info, Metadata $md, Cancellation $cancellation): \stdClass => $response,
        );

        Assert::same($result, $response);
    }

    public function rejectsUnauthenticatedUnaryCallsBeforeTheHandler(): void
    {
        $called = false;
        $thrown = null;

        try {
            new ServerInterceptor(StaticAuth::bearer('right'))->interceptUnary(
                new \stdClass(),
                new StreamInfo('/svc/Method', RpcType::Unary),
                new Metadata(),
                new NullCancellation(),
                static function (object $request, StreamInfo $info, Metadata $md, Cancellation $cancellation) use (&$called): \stdClass {
                    $called = true;

                    return new \stdClass();
                },
            );
        } catch (InvokeError $e) {
            $thrown = $e;
        }

        Assert::instanceOf($thrown, InvokeError::class);
        Assert::same($thrown->statusCode, Code::UNAUTHENTICATED);
        Assert::false($called);
    }

    public function rejectsUnauthenticatedStreamCallsBeforeTheHandler(): void
    {
        $called = false;
        $thrown = null;

        try {
            new ServerInterceptor(StaticAuth::bearer('right'))->interceptStream(
                new FakeServerStream(),
                new StreamInfo('/svc/Stream', RpcType::BidirectionalStream),
                StaticAuth::bearer('wrong')->apply(new Metadata(), new NullCancellation()),
                new NullCancellation(),
                static function (ServerStream $stream, StreamInfo $info, Metadata $md, Cancellation $cancellation) use (&$called): void {
                    $called = true;
                },
            );
        } catch (InvokeError $e) {
            $thrown = $e;
        }

        Assert::instanceOf($thrown, InvokeError::class);
        Assert::same($thrown->statusCode, Code::UNAUTHENTICATED);
        Assert::false($called);
    }
}

/**
 * @template-implements ServerStream<\stdClass, \stdClass>
 */
final class FakeServerStream implements ServerStream
{
    public Metadata $headers;

    public Metadata $trailers;

    public function __construct()
    {
        $this->headers = new Metadata();
        $this->trailers = new Metadata();
    }

    #[\Override]
    public function send(object $message): void {}

    #[\Override]
    public function receive(): object
    {
        throw new \LogicException('FakeServerStream is not consumed.');
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from [];
    }

    #[\Override]
    public function close(): void {}
}
