<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture\Tests;

use PierreGranger\Ecriture\ApidaeClient;

/**
 * Client factice : enregistre les appels et renvoie les réponses préparées, dans l'ordre.
 */
final class FakeClient implements ApidaeClient
{
    /** @var list<array{path: string, params: array<string, mixed>}> */
    public array $calls = [];

    /** @param list<array<string, mixed>> $responses */
    public function __construct(private array $responses = [['code' => 200]])
    {
    }

    public function request(string $path, array $params): array
    {
        $this->calls[] = ['path' => $path, 'params' => $params];
        return count($this->responses) > 1 ? array_shift($this->responses) : $this->responses[0];
    }

    /** @return array<string, mixed> */
    public function lastPostfields(): array
    {
        return end($this->calls)['params']['POSTFIELDS'];
    }
}
