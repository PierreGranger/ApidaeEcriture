<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture\Tests;

use PHPUnit\Framework\TestCase;
use PierreGranger\ApidaeException;

class ApidaeEcritureTest extends TestCase
{
    private function ecriture(): ApidaeEcritureTestable
    {
        return new ApidaeEcritureTestable([
            'projet_ecriture_clientId' => 'client',
            'projet_ecriture_secret' => 'super-secret',
        ]);
    }

    public function testSecretIsNotPublic(): void
    {
        $property = new \ReflectionProperty(ApidaeEcritureTestable::class, 'projet_ecriture_secret');
        $this->assertFalse($property->isPublic());
        $this->assertStringNotContainsString('super-secret', json_encode(get_object_vars($this->ecriture())));
    }

    public function testEnregistrerSendsTokenAndSetsLastId(): void
    {
        $ae = $this->ecriture();
        $ae->responses = [['code' => 200, 'status' => 'MODIFICATION_VALIDATION_SKIPPED', 'id' => 123]];

        $this->assertTrue($ae->modification(['id' => 123, 'root' => ['nom' => ['libelleFr' => 'x']], 'fieldList' => ['nom']]));

        $call = $ae->apiCalls()[0];
        $this->assertSame('/api/v002/ecriture/', $call['path']);
        $this->assertSame('token-1', $call['params']['token']);
        $this->assertSame('123', $ae->last_id);
    }

    public function testEnregistrerFailureWithoutErrorTypeThrowsAndResetsLastId(): void
    {
        $ae = $this->ecriture();
        $ae->responses = [
            ['code' => 200, 'status' => 'CREATION_VALIDATION_SKIPPED', 'id' => 1],
            ['code' => 500, 'message' => 'Internal error'],
        ];
        $ae->creation(['type' => 'EQUIPEMENT', 'root' => []]);
        $this->assertSame('1', $ae->last_id);

        try {
            $ae->modification(['id' => 2, 'root' => []]);
            $this->fail('Une exception était attendue');
        } catch (ApidaeException $e) {
            $this->assertSame(ApidaeException::INVALID_HTTPCODE, $e->getCode());
        }
        $this->assertNull($ae->last_id);
    }

    public function testExpiredTokenIsRenewedAndRequestReplayedOnce(): void
    {
        $ae = $this->ecriture();
        $ae->responses = [
            ['code' => 401, 'error' => 'invalid_token'],
            ['code' => 200, 'status' => 'MODIFICATION_NO_DIFF'],
        ];

        $this->assertTrue($ae->modification(['id' => 1, 'root' => []]));

        $calls = $ae->apiCalls();
        $this->assertCount(2, $calls);
        $this->assertSame('token-1', $calls[0]['params']['token']);
        $this->assertSame('token-2', $calls[1]['params']['token']);
    }

    public function testPersistent401Throws(): void
    {
        $ae = $this->ecriture();
        $ae->responses = [['code' => 401, 'error' => 'invalid_token'], ['code' => 401, 'error' => 'invalid_token']];

        $this->expectException(ApidaeException::class);
        $ae->modification(['id' => 1, 'root' => []]);
    }

    public function testCollaborateursSendsTokenSso(): void
    {
        $ae = $this->ecriture();
        $ae->responses = [['code' => 200]];

        $ae->putCollaborateurs(12, [['id' => 1]], 'sso-token');

        $this->assertSame('sso-token', $ae->apiCalls()[0]['params']['POSTFIELDS']['tokenSSO']);
    }

    public function testDonneesPriveesSendsTokenSso(): void
    {
        $ae = $this->ecriture();
        $ae->responses = [['code' => 200, 'status' => 'MODIFICATION_DONNEES_PRIVEES']];

        $ae->putDescriptifsPrives(12, 'cle', ['fr' => 'Valeur'], 'sso-token');

        $this->assertSame('sso-token', $ae->apiCalls()[0]['params']['POSTFIELDS']['tokenSSO']);
    }

    public function testCritereInterneAddsWarningToLastResult(): void
    {
        $ae = $this->ecriture();
        $ae->responses = [['code' => 200, 'status' => 'AJOUT_CRITERES', 'id' => [1]]];

        $this->assertTrue($ae->critereInterne('PUT', [1, 2], [10]));
        $this->assertStringContainsString('2 not updated', $ae->lastResult()['warning']);
    }

    public function testAutorisation(): void
    {
        $ae = $this->ecriture();
        $ae->responses = [['code' => 200, 'body' => '"MODIFICATION_POSSIBLE"'], ['code' => 404, 'body' => '']];

        $this->assertTrue($ae->autorisation(1));
        $this->assertSame('MODIFICATION_POSSIBLE', $ae->lastAutorisationDetail());
        $this->assertFalse($ae->autorisation(2));
        $this->assertSame('HTTPCODE_404', $ae->lastAutorisationDetail());
    }

    public function testGetCurlValueRejectsMissingFile(): void
    {
        $this->expectException(\Exception::class);
        $this->ecriture()->getCurlValue('/nonexistent/file.jpg', 'image/jpeg', 'file.jpg');
    }
}
