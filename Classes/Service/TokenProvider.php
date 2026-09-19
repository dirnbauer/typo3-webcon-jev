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
            $token = null;
        }

        $token = Cast::trimmed($token);

        return $token !== '' ? $token : $this->fromEnvironment();
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

        try {
            if ($this->vault->exists($identifier)) {
                return sprintf('nr-vault (%s)', $identifier);
            }
        } catch (Throwable) {
            // Fall through to the environment.
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
