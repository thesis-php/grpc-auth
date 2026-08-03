<?php

declare(strict_types=1);

namespace Thesis\Grpc\Auth;

use Amp\Cancellation;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\Server\StreamInterceptor;
use Thesis\Grpc\Server\UnaryInterceptor;
use Thesis\Grpc\ServerStream;

/**
 * @api
 */
final readonly class ServerInterceptor implements
    UnaryInterceptor,
    StreamInterceptor
{
    public function __construct(
        private Authenticator $authenticator,
    ) {}

    #[\Override]
    public function interceptUnary(
        object $request,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $handler,
    ): object {
        $this->authenticator->authenticate($md, $cancellation);

        return $handler($request, $info, $md, $cancellation);
    }

    #[\Override]
    public function interceptStream(
        ServerStream $stream,
        StreamInfo $info,
        Metadata $md,
        Cancellation $cancellation,
        callable $next,
    ): void {
        $this->authenticator->authenticate($md, $cancellation);

        $next($stream, $info, $md, $cancellation);
    }
}
