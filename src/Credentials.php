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
interface Credentials
{
    /**
     * @throws InvokeError with {@see Code::UNAUTHENTICATED} if credentials cannot be obtained
     */
    public function apply(Metadata $md, Cancellation $cancellation): Metadata;
}
