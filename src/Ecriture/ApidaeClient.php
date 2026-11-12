<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture;

/**
 * Ce dont les services d'écriture ont besoin pour parler à l'API Apidae :
 * une requête authentifiée avec le token du projet d'écriture.
 *
 * Les services ne dépendent que de cette interface (et non plus de ApidaeCore par héritage),
 * ce qui permet de les tester avec un faux client.
 */
interface ApidaeClient
{
    /**
     * Exécute une requête authentifiée (le token du projet est ajouté par le client).
     *
     * @param array<string, mixed> $params options au format de ApidaeCore::request() (POSTFIELDS, CUSTOMREQUEST, format, header…)
     * @return array<string, mixed> réponse au format de ApidaeCore::request() : code, header, body + clés du JSON décodé
     */
    public function request(string $path, array $params): array;
}
