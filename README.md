# thesis/grpc-auth

Authentication interceptors for [thesis/grpc](https://github.com/thesis-php/grpc): the client attaches
credentials to every outgoing call, the server verifies them and rejects unauthenticated calls with
`UNAUTHENTICATED` — both for unary and streaming RPCs.

## Contents

- [Installation](#installation)
- [Usage](#usage)
- [StaticAuth](#staticauth)
- [Custom credentials and authenticators](#custom-credentials-and-authenticators)

## Installation

```bash
composer require thesis/grpc-auth
```

## Usage

Two interceptors, one per side. The client one takes [Credentials](#custom-credentials-and-authenticators)
(what to attach), the server one an [Authenticator](#custom-credentials-and-authenticators) (how to
verify). [StaticAuth](#staticauth) is both, so a shared secret wires up both ends:

```php
use Thesis\Grpc\Auth;
use Thesis\Grpc\Client;
use Thesis\Grpc\Server;

$auth = Auth\StaticAuth::bearer('super-secret-token');

// Client — attaches "authorization: Bearer super-secret-token" to every call
$client = new Client\Builder()
    ->withUnaryInterceptors(new Auth\ClientInterceptor($auth))
    ->withStreamInterceptors(new Auth\ClientInterceptor($auth))
    ->build();

// Server — rejects any call whose authorization does not match
$server = new Server\Builder()
    ->withUnaryInterceptors(new Auth\ServerInterceptor($auth))
    ->withStreamInterceptors(new Auth\ServerInterceptor($auth))
    ->build();
```

The server interceptor runs the authenticator **before** the handler, so an unauthenticated call never
reaches your service.

## StaticAuth

`StaticAuth` is a fixed shared-secret credential sent in the `authorization` header as
`"<scheme> <secret>"`. It implements both `Credentials` and `Authenticator`: on the client it attaches
the header, on the server it matches the scheme case-insensitively (per RFC 7235) and compares only the
secret in constant time (`hash_equals`).

```php
Auth\StaticAuth::bearer('token');        // authorization: Bearer token
Auth\StaticAuth::basic('user', 'pass');  // authorization: Basic base64(user:pass)
new Auth\StaticAuth('Custom', 'abc123'); // any scheme and its secret
```

## Custom credentials and authenticators

Implement `Credentials` for a client that fetches or rotates a token per call (e.g. OAuth). It is
resolved on every call and may use the `Cancellation` to bound the work:

```php
use Amp\Cancellation;
use Thesis\Grpc\Auth\Credentials;
use Thesis\Grpc\Metadata;

final readonly class OAuthCredentials implements Credentials
{
    public function __construct(private TokenProvider $tokens) {}

    public function apply(Metadata $md, Cancellation $cancellation): Metadata
    {
        return $md->with('authorization', 'Bearer ' . $this->tokens->accessToken($cancellation));
    }
}
```

Implement `Authenticator` for server-side verification, validate the metadata and throw
`InvokeError(Code::UNAUTHENTICATED)` when it fails:

```php
use Amp\Cancellation;
use Google\Rpc\Code;
use Thesis\Grpc\Auth\Authenticator;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;

final readonly class JwtAuthenticator implements Authenticator
{
    public function __construct(private JwtVerifier $verifier) {}

    public function authenticate(Metadata $md, Cancellation $cancellation): void
    {
        $header = $md->value('authorization');

        if ($header === null || !$this->verifier->verify($header)) {
            throw new InvokeError(Code::UNAUTHENTICATED, 'invalid token');
        }
    }
}
```
