<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture;

use PierreGranger\ApidaeException;

/**
 * Vérification commune des réponses de l'API d'écriture.
 */
final class Response
{
    /**
     * Lève une ApidaeException si la réponse n'est pas un succès HTTP (2xx).
     * Le code de l'exception est le code HTTP, comme précédemment.
     *
     * @param array<string, mixed> $result
     */
    public static function assertSuccess(array $result): void
    {
        if (!isset($result['code'])) {
            throw new ApidaeException('Unknown error', 0, ['result' => $result]);
        }

        $code = (int) $result['code'];
        if ($code >= 200 && $code < 300) {
            return;
        }

        $message = 'Unknown error';
        if (isset($result['errorType'])) {
            $message = $result['errorType'] . (isset($result['message']) ? ' ' . $result['message'] : '');
        } elseif (isset($result['message'])) {
            $message = (string) $result['message'];
        } elseif (isset($result['error'])) {
            // Erreurs OAuth : {"error":"invalid_token","error_description":"..."}
            $message = $result['error'] . (isset($result['error_description']) ? ' ' . $result['error_description'] : '');
        }

        throw new ApidaeException($message, $code, ['result' => $result]);
    }

    /**
     * json_encode qui lève une exception plutôt que de renvoyer false (UTF-8 invalide…).
     */
    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
