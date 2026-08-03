<?php

declare(strict_types=1);

namespace Thesis\Grpc\Auth;

use Amp\NullCancellation;
use Google\Rpc\Code;
use Testo\Assert;
use Testo\Test;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;

#[Test]
final class StaticAuthTest
{
    public function bearerAttachesAndVerifiesTheHeader(): void
    {
        $auth = StaticAuth::bearer('secret-token');

        $md = $auth->apply(new Metadata(), new NullCancellation());
        Assert::same($md->value('authorization'), 'Bearer secret-token');

        $auth->authenticate($md, new NullCancellation());
    }

    public function basicEncodesTheCredentials(): void
    {
        $md = StaticAuth::basic('user', 'pass')->apply(new Metadata(), new NullCancellation());

        Assert::same($md->value('authorization'), 'Basic ' . base64_encode('user:pass'));
    }

    public function matchesTheSchemeCaseInsensitively(): void
    {
        $md = new Metadata()->with('authorization', 'bearer secret-token');
        Assert::same($md->value('authorization'), 'bearer secret-token');

        StaticAuth::bearer('secret-token')->authenticate($md, new NullCancellation());
    }

    public function toleratesExtraWhitespaceBetweenSchemeAndToken(): void
    {
        $md = new Metadata()->with('authorization', "Bearer  \tsecret-token");
        Assert::same($md->value('authorization'), "Bearer  \tsecret-token");

        StaticAuth::bearer('secret-token')->authenticate($md, new NullCancellation());
    }

    public function rejectsMismatchedCredentials(): void
    {
        $md = StaticAuth::bearer('wrong')->apply(new Metadata(), new NullCancellation());

        try {
            StaticAuth::bearer('right')->authenticate($md, new NullCancellation());
        } catch (InvokeError $e) {
            Assert::same($e->statusCode, Code::UNAUTHENTICATED);

            return;
        }

        Assert::fail('Expected UNAUTHENTICATED for mismatched credentials.');
    }

    public function rejectsMissingCredentials(): void
    {
        try {
            StaticAuth::bearer('t')->authenticate(new Metadata(), new NullCancellation());
        } catch (InvokeError $e) {
            Assert::same($e->statusCode, Code::UNAUTHENTICATED);

            return;
        }

        Assert::fail('Expected UNAUTHENTICATED for missing credentials.');
    }
}
