<?php

declare(strict_types=1);

namespace Thesis\Grpc\Auth;

use Amp\Cancellation;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;

/**
 * @api
 */
interface Authenticator
{
    /**
     * @throws InvokeError with {@see Code::UNAUTHENTICATED} when the request is not authenticated
     */
    public function authenticate(Metadata $md, Cancellation $cancellation): void;
}
