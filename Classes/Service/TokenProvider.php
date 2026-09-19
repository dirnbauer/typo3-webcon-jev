<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service;

use Netresearch\NrVault\Service\VaultServiceInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Http\ApplicationType;
use Webconsulting\WebconJev\Configuration\Settings;
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
    public const ENVIRONMENT_VARIABLE = 'TYPESAFE_API_KEY';

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
     * Where the token that would be used right now comes from — for the module's status panel.
     */
    public function describeSource(): string
    {
        $identifier = $this->settings->tokenIdentifier();

        // exists() needs no read permission and retrieve() does, so the two disagree in exactly
        // the case worth naming: the secret is in the vault, and this context may not read it.
        // Reporting only the first produced a status table that said the token was there directly
        // above an error saying there was none.
        $present = false;
        try {
            $present = $this->vault->exists($identifier);
        } catch (Throwable) {
            // Treat an unreadable vault as absent and fall through to the environment.
        }

        if ($present) {
            // Deliberately the vault read and not getToken(): the environment fallback would
            // otherwise let this report "nr-vault" for a value that came from the environment.
            if ($this->fromVault() !== null) {
                return sprintf('nr-vault (%s)', $identifier);
            }

            return $this->fromEnvironment() !== null
                ? sprintf('environment (%s) — the vault has it too, unreadable from here', self::ENVIRONMENT_VARIABLE)
                : sprintf('nr-vault (%s) — present, but not readable from here', $identifier);
        }

        return $this->fromEnvironment() !== null
            ? sprintf('environment (%s)', self::ENVIRONMENT_VARIABLE)
            : 'not configured';
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
