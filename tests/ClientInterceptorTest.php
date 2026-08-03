<?php

declare(strict_types=1);

namespace Thesis\Grpc\Auth;

use Amp\Cancellation;
use Amp\NullCancellation;
use Testo\Assert;
use Testo\Test;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\RpcType;

#[Test]
final class ClientInterceptorTest
{
    public function appliesCredentialsToUnaryCalls(): void
    {
        $seen = null;
        $response = new \stdClass();

        /** @var Invoke<\stdClass, \stdClass> $invoke */
        $invoke = new Invoke('/svc/Method', \stdClass::class, RpcType::Unary);

        $result = new ClientInterceptor(StaticAuth::bearer('t'))->interceptUnary(
            new \stdClass(),
            $invoke,
            new Metadata(),
            new NullCancellation(),
            static function (object $request, Invoke $invoke, Metadata $md, Cancellation $cancellation) use (&$seen, $response): \stdClass {
                $seen = $md;

                return $response;
            },
        );

        Assert::same($result, $response);
        Assert::instanceOf($seen, Metadata::class);
        Assert::same($seen->value('authorization'), 'Bearer t');
    }

    public function appliesCredentialsToStreamCalls(): void
    {
        $seen = null;

        /** @var Invoke<\stdClass, \stdClass> $invoke */
        $invoke = new Invoke('/svc/Stream', \stdClass::class, RpcType::ServerStream);

        new ClientInterceptor(StaticAuth::bearer('t'))->interceptStream(
            $invoke,
            new Metadata(),
            new NullCancellation(),
            static function (Invoke $invoke, Metadata $md, Cancellation $cancellation) use (&$seen): ClientStream {
                $seen = $md;

                return new FakeClientStream();
            },
        );

        Assert::instanceOf($seen, Metadata::class);
        Assert::same($seen->value('authorization'), 'Bearer t');
    }
}

/**
 * @template-implements ClientStream<\stdClass, \stdClass>
 */
final class FakeClientStream implements ClientStream
{
    #[\Override]
    public function send(object $message): void {}

    #[\Override]
    public function receive(): object
    {
        throw new \LogicException('FakeClientStream is not consumed.');
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from [];
    }

    #[\Override]
    public function headers(): Metadata
    {
        return new Metadata();
    }

    #[\Override]
    public function trailers(Cancellation $cancellation = new NullCancellation()): Metadata
    {
        return new Metadata();
    }

    #[\Override]
    public function close(): void {}
}
