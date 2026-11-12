<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture\Tests;

use PierreGranger\ApidaeEcriture;

/**
 * Façade dont request() est simulé : /oauth/token renvoie un token numéroté, les autres chemins
 * renvoient les réponses de $responses dans l'ordre.
 */
final class ApidaeEcritureTestable extends ApidaeEcriture
{
    /** @var list<array{path: string, params: mixed}> */
    public array $calls = [];

    /** @var list<array<string, mixed>> */
    public array $responses = [];

    public int $tokensIssued = 0;

    protected function request(string $path, $params = null)
    {
        $this->calls[] = ['path' => $path, 'params' => $params];

        if ($path === '/oauth/token') {
            $this->tokensIssued++;
            $result = ['code' => 200, 'access_token' => 'token-' . $this->tokensIssued, 'expires_in' => 3600];
        } else {
            $result = array_shift($this->responses) ?? ['code' => 200];
        }

        if (isset($params['POSTFIELDS'])) {
            $this->lastPostfields = $params['POSTFIELDS'];
        }
        $this->lastResult = $result;
        return $result;
    }

    /** @return list<array{path: string, params: mixed}> */
    public function apiCalls(): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c['path'] !== '/oauth/token'));
    }
}
