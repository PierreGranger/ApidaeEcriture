<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture;

/**
 * Adaptateur ApidaeClient construit à partir de closures.
 *
 * Utilisé par la façade ApidaeEcriture pour exposer aux services son request() (protégé) et son
 * cache de token, sans que les services aient à hériter de ApidaeCore.
 *
 * En cas de réponse 401 (token expiré ou révoqué), le token est oublié et la requête rejouée une fois.
 */
final class ClosureClient implements ApidaeClient
{
    /**
     * @param \Closure(string, array<string, mixed>): array<string, mixed> $request
     * @param \Closure(): string $token
     * @param \Closure(): void $forgetToken
     */
    public function __construct(
        private readonly \Closure $request,
        private readonly \Closure $token,
        private readonly \Closure $forgetToken,
    ) {
    }

    public function request(string $path, array $params): array
    {
        $params['token'] = ($this->token)();
        $result = ($this->request)($path, $params);

        if (($result['code'] ?? null) == 401) {
            ($this->forgetToken)();
            $params['token'] = ($this->token)();
            $result = ($this->request)($path, $params);
        }

        return $result;
    }
}
