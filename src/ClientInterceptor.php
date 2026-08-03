<?php

declare(strict_types=1);

namespace Thesis\Grpc\Auth;

use Amp\Cancellation;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\StreamInterceptor;
use Thesis\Grpc\Client\UnaryInterceptor;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
final readonly class ClientInterceptor implements
    UnaryInterceptor,
    StreamInterceptor
{
    public function __construct(
        private Credentials $credentials,
    ) {}

    #[\Override]
    public function interceptUnary(
        object $request,
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $invoker,
    ): object {
        return $invoker($request, $invoke, $this->credentials->apply($md, $cancellation), $cancellation);
    }

    #[\Override]
    public function interceptStream(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $newStream,
    ): ClientStream {
        return $newStream($invoke, $this->credentials->apply($md, $cancellation), $cancellation);
    }
}
