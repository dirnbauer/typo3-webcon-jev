<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service;

use Netresearch\NrVault\Service\VaultServiceInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Http\ApplicationType;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Service\Dto\TokenSource;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Where the Jev API token comes from.
 *
 * The vault is the source of truth. TYPESAFE_API_KEY is only a development fallback, so a
 * checkout with the key in .ddev/config.local.yaml works before anyone seeds the vault.
 *
 * A frontend request reads through retrieveForFrontend(), which resolves only secrets explicitly
 * flagged frontend-accessible — the flag "webcon-jev:token:import" sets, because the powermail
 * condition endpoint that needs the token runs with no backend user. The token itself never
 * leaves the server: only the decision does.
 */
final readonly class TokenProvider
{
    public const string ENVIRONMENT_VARIABLE = 'TYPESAFE_API_KEY';

    public function __construct(
        private VaultServiceInterface $vault,
        private Settings $settings,
        private LoggerInterface $logger,
    ) {}

    public function getToken(): ?string
    {
        return $this->fromVault() ?? $this->fromEnvironment();
    }

    /**
     * The token as this context may read it, or null when it may not.
     */
    private function fromVault(): ?string
    {
        $identifier = $this->settings->tokenIdentifier();

        try {
            $token = $this->isFrontendRequest()
                ? $this->vault->retrieveForFrontend($identifier)
                : $this->vault->retrieve($identifier);
        } catch (Throwable $exception) {
            $this->logger->notice('Could not read the Jev token from the vault.', [
                'identifier' => $identifier,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        $token = Cast::trimmed($token);

        return $token !== '' ? $token : null;
    }

    public function hasToken(): bool
    {
        return $this->getToken() !== null;
    }

    /**
     * Whether a token is there at all, without reading it.
     *
     * Cheap enough for every page view of the backend module: exists() neither decrypts the secret
     * nor writes an entry to the vault's audit log, which a read does.
     */
    public function isPresent(): bool
    {
        try {
            if ($this->vault->exists($this->settings->tokenIdentifier())) {
                return true;
            }
        } catch (Throwable) {
            // An unreadable vault counts as empty; the environment may still have one.
        }

        return $this->fromEnvironment() !== null;
    }

    /**
     * Whether a frontend request would be able to read the token.
     *
     * This is the question that actually matters for the powermail integrations, and the one no
     * other check answers: they run with no backend user, so they resolve the secret through its
     * `frontend_accessible` flag and nothing else. A CLI check that reads as an admin or a
     * technical actor proves the token exists and the API is reachable, and says nothing about
     * whether a visitor filling in a form will get a decision or a fallback.
     *
     * Safe to call from anywhere: retrieveForFrontend() refuses a secret without the flag whoever
     * asks, so this never reports more access than a real visitor would have.
     */
    public function isReadableByFrontend(): bool
    {
        try {
            return Cast::trimmed($this->vault->retrieveForFrontend($this->settings->tokenIdentifier())) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Where the token that would be used right now comes from.
     */
    public function source(): TokenSource
    {
        // exists() needs no read permission and retrieve() does, so the two disagree in exactly
        // the case worth naming: the secret is in the vault, and this context may not read it.
        // Reporting only the first produced a status table that said the token was there directly
        // above an error saying there was none.
        $present = false;
        try {
            $present = $this->vault->exists($this->settings->tokenIdentifier());
        } catch (Throwable) {
            // Treat an unreadable vault as absent and fall through to the environment.
        }

        $inEnvironment = $this->fromEnvironment() !== null;

        return match (true) {
            // Deliberately the vault read and not getToken(): the environment fallback would
            // otherwise let this report the vault for a value that came from the environment.
            $present && $this->fromVault() !== null => TokenSource::Vault,
            $present && $inEnvironment => TokenSource::EnvironmentWhileVaultUnreadable,
            $present => TokenSource::VaultUnreadable,
            $inEnvironment => TokenSource::Environment,
            default => TokenSource::None,
        };
    }

    /**
     * The source in a sentence, for the command line.
     */
    public function describeSource(): string
    {
        $identifier = $this->settings->tokenIdentifier();

        return match ($this->source()) {
            TokenSource::Vault => sprintf('nr-vault (%s)', $identifier),
            TokenSource::EnvironmentWhileVaultUnreadable => sprintf('environment (%s) — the vault has it too, unreadable from here', self::ENVIRONMENT_VARIABLE),
            TokenSource::VaultUnreadable => sprintf('nr-vault (%s) — present, but not readable from here', $identifier),
            TokenSource::Environment => sprintf('environment (%s)', self::ENVIRONMENT_VARIABLE),
            TokenSource::None => 'not configured',
        };
    }

    private function fromEnvironment(): ?string
    {
        $value = Cast::trimmed(getenv(self::ENVIRONMENT_VARIABLE));

        return $value !== '' ? $value : null;
    }

    private function isFrontendRequest(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        return $request instanceof ServerRequestInterface
            && ApplicationType::fromRequest($request)->isFrontend();
    }
}
