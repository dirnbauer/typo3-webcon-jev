<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend;

use Psr\Http\Message\ServerRequestInterface;
use Webconsulting\WebconJev\Support\Cast;

/**
 * What an AJAX request to the module sent: a JSON body, or a form-encoded one.
 */
final class RequestPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(ServerRequestInterface $request): array
    {
        $body = (string)$request->getBody();
        $decoded = $body !== '' ? Cast::map(json_decode($body, true)) : [];

        return $decoded !== [] ? $decoded : Cast::map($request->getParsedBody());
    }
}
