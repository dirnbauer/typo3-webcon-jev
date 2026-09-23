<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Webconsulting\WebconJev\Service\Dto\TokenSource;
use Webconsulting\WebconJev\Service\TokenProvider;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * Where the token comes from, as the connection page and the ping command report it. The vault is
 * empty here, so these are the environment's cases.
 */
final class TokenProviderTest extends AbstractJevTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv(TokenProvider::ENVIRONMENT_VARIABLE);
    }

    protected function tearDown(): void
    {
        putenv(TokenProvider::ENVIRONMENT_VARIABLE);
        parent::tearDown();
    }

    #[Test]
    public function withNoTokenAnywhereEveryCheckSaysSo(): void
    {
        $provider = $this->get(TokenProvider::class);

        self::assertFalse($provider->isPresent());
        self::assertFalse($provider->hasToken());
        self::assertFalse($provider->isReadableByFrontend());
        self::assertSame(TokenSource::None, $provider->source());
        self::assertSame('not configured', $provider->describeSource());
    }

    #[Test]
    public function aTokenInTheEnvironmentIsOneTheFrontendCanUseToo(): void
    {
        putenv(TokenProvider::ENVIRONMENT_VARIABLE . '=  test-token  ');
        $provider = $this->get(TokenProvider::class);

        self::assertTrue($provider->isPresent());
        self::assertSame('test-token', $provider->getToken());
        self::assertTrue($provider->isReadableByFrontend(), 'a frontend request falls back to the environment as well');
        self::assertSame(TokenSource::Environment, $provider->source());
        self::assertSame('environment (TYPESAFE_API_KEY)', $provider->describeSource());
    }
}
