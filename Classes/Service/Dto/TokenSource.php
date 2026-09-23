<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service\Dto;

/**
 * Where the token that would be used right now comes from — and the two cases where the vault
 * and what this context may read disagree, which are the ones worth naming.
 */
enum TokenSource: string
{
    case Vault = 'vault';
    case Environment = 'environment';

    /** The environment's token is used; the vault has one too, which this context may not read. */
    case EnvironmentWhileVaultUnreadable = 'environmentWhileVaultUnreadable';

    /** The vault has a token, this context may not read it, and the environment has none. */
    case VaultUnreadable = 'vaultUnreadable';

    case None = 'none';
}
