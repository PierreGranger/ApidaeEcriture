<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture\Tests;

use PHPUnit\Framework\TestCase;
use PierreGranger\ApidaeException;
use PierreGranger\Ecriture\CollaborateursService;
use PierreGranger\Ecriture\CriteresInternesService;
use PierreGranger\Ecriture\DonneesPriveesService;
use PierreGranger\Ecriture\EcritureService;

class ServicesTest extends TestCase
{
    public function testMediaKeyCannotOverrideValidatedFields(): void
    {
        $service = new EcritureService(new FakeClient());

        $this->expectExceptionMessage('clé de media invalide');
        $service->buildPostfields([
            'mode' => 'CREATION',
            'type' => 'EQUIPEMENT',
            'root' => [],
            'medias' => ['mode' => new \CURLFile(__FILE__)],
        ]);
    }

    public function testMediaMustBeACurlFile(): void
    {
        $service = new EcritureService(new FakeClient());

        $this->expectExceptionMessage('doit être un CURLFile');
        $service->buildPostfields([
            'mode' => 'CREATION',
            'type' => 'EQUIPEMENT',
            'medias' => ['multimedia.illustration-1' => '@/etc/passwd'],
        ]);
    }

    public function testBuildPostfields(): void
    {
        $file = new \CURLFile(__FILE__);
        $postfields = (new EcritureService(new FakeClient(), skipValidation: true))->buildPostfields([
            'mode' => 'CREATION',
            'type' => 'FETE_ET_MANIFESTATION',
            'root' => ['nom' => ['libelleFr' => 'Test']],
            'fieldlist' => ['nom'],
            'aspect.HIVER.root' => [],
            'aspect.HIVER.root.fieldList' => ['nom'],
            'medias' => ['multimedia.illustration-1' => $file],
        ]);

        $this->assertSame('CREATION', $postfields['mode']);
        $this->assertSame('true', $postfields['skipValidation']);
        $this->assertSame('{"nom":{"libelleFr":"Test"}}', $postfields['root']);
        $this->assertSame('["nom"]', $postfields['root.fieldList']);
        $this->assertSame('{}', $postfields['aspect.HIVER.root']);
        $this->assertSame('["root","aspect.HIVER.root"]', $postfields['fields']);
        $this->assertSame($file, $postfields['multimedia.illustration-1']);
    }

    public function testInvalidUtf8Throws(): void
    {
        $this->expectException(\JsonException::class);
        (new EcritureService(new FakeClient()))->buildPostfields([
            'mode' => 'MODIFICATION', 'id' => 1, 'root' => ['nom' => ['libelleFr' => "\xB1\x31"]],
        ]);
    }

    public function testErrorTypeKeepsHistoricalException(): void
    {
        $service = new EcritureService(new FakeClient([['code' => 403, 'errorType' => 'ECRITURE_FORBIDDEN']]));

        try {
            $service->enregistrer(['mode' => 'MODIFICATION', 'id' => 1, 'root' => []]);
            $this->fail('Une exception était attendue');
        } catch (ApidaeException $e) {
            $this->assertSame('ecriture_error', $e->getMessage());
            $this->assertSame('ECRITURE_FORBIDDEN', $e->getDetails()['errorType']);
        }
    }

    public function testCollaborateursErrorUsesHttpCode(): void
    {
        $service = new CollaborateursService(new FakeClient([['code' => 403, 'errorType' => 'FORBIDDEN', 'message' => 'Non']]));

        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('FORBIDDEN Non');
        $service->collaborateurs('PUT', 1, []);
    }

    public function testDescriptifsPrivesPutRequiresValeurs(): void
    {
        $this->expectExceptionMessage('$valeurs is required');
        (new DonneesPriveesService(new FakeClient()))->descriptifsPrives('PUT', 1, 'cle', null);
    }

    public function testCritereInterneReindexesIdsAndToleratesBadResponse(): void
    {
        $client = new FakeClient([['code' => 200, 'status' => 'AJOUT_CRITERES', 'id' => 'not-an-array']]);
        $ret = (new CriteresInternesService($client))->critereInterne('PUT', [1, 1, 2], [3]);

        $this->assertFalse($ret['ok']);
        // array_unique laissait des trous dans les clés : JSON objet {"0":1,"2":2} au lieu de [1,2]
        $this->assertSame('{"id":[1,2],"criteresInternesAAjouter":[3]}', $client->lastPostfields()['criteres']);
    }
}
