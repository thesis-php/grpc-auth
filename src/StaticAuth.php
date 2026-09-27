<?php

declare(strict_types=1);

namespace Thesis\Grpc\Auth;

use Amp\Cancellation;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;

/**
 * A fixed shared-secret credential, sent in the `authorization` header as `"<scheme> <secret>"`. It
 * serves both sides: as {@see Credentials} it attaches the header on the client, as {@see Authenticator}
 * it verifies it on the server — matching the scheme case-insensitively and comparing
 * only the secret in constant time.
 *
 * @api
 */
final readonly class StaticAuth implements
    Credentials,
    Authenticator
{
    private const string HEADER = 'authorization';

    /**
     * @param non-empty-string $scheme
     * @param non-empty-string $secret
     */
    public function __construct(
        private string $scheme,
        private string $secret,
    ) {}

    /**
     * @param non-empty-string $token
     */
    public static function bearer(string $token): self
    {
        return new self('Bearer', $token);
    }

    /**
     * @param non-empty-string $username
     * @param non-empty-string $password
     */
    public static function basic(string $username, string $password): self
    {
        return new self('Basic', base64_encode("{$username}:{$password}"));
    }

    #[\Override]
    public function apply(Metadata $md, Cancellation $cancellation): Metadata
    {
        return $md->with(self::HEADER, "{$this->scheme} {$this->secret}");
    }

    #[\Override]
    public function authenticate(Metadata $md, Cancellation $cancellation): void
    {
        $secret = $md->value(self::HEADER);

        if ($secret === null || !$this->matches($secret)) {
            throw new InvokeError(Code::UNAUTHENTICATED, 'invalid or missing credentials');
        }
    }

    /**
     * @param non-empty-string $header
     */
    private function matches(string $header): bool
    {
        $chunks = preg_split('/\s+/', trim($header), 2);

        if ($chunks === false) {
            $chunks = [];
        }

        return strcasecmp($chunks[0] ?? '', $this->scheme) === 0 && hash_equals($this->secret, $chunks[1] ?? '');
    }
}
