<?php

namespace PierreGranger;

/**
 *
 * @author  Pierre Granger <pierre@pierre-granger.fr>
 *
 */

trait Collaborateurs
{
    public function putCollaborateurs($id, $membresCollaborateurs, $tokenSSO=null)
    {
        return $this->collaborateurs('PUT', $id, $membresCollaborateurs, $tokenSSO) ;
    }
    public function deleteCollaborateurs($id, $membresCollaborateurs, $tokenSSO=null)
    {
        return $this->collaborateurs('DELETE', $id, $membresCollaborateurs, $tokenSSO) ;
    }

    private function collaborateurs($method, $id, $membresCollaborateurs, $tokenSSO)
    {
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'])) {
            throw new \Exception('bad method');
        }

        if (! is_int($id)) {
            throw new \Exception($id.' is not a valid id');
        }

        if (! is_array($membresCollaborateurs)) {
            throw new \Exception('$membresCollaborateurs is not an array');
        }

        $POSTFIELDS = ['collaborateurs' => json_encode([
            'id' => $id,
            'membresCollaborateurs' => $membresCollaborateurs
        ])];

        $access_token = $this->gimme_token();

        $requestData = [
            'token' => $access_token,
            'POSTFIELDS' => $POSTFIELDS,
            'CUSTOMREQUEST' => $method,
            'format' => 'json'
        ];

        if ($tokenSSO !== null) {
            $requestData['tokenSSO'] = $tokenSSO;
        }

        $result = $this->request('/api/v002/collaborateurs/', $requestData);
        $lastResult = $this->lastResult() ;

        if (!isset($result['code'])) {
            throw new \Exception('Unknown error', $lastResult->code);
        }

        if ($result['code'] != 200) {
            if (isset($result['errorType'])) {
                throw new \Exception($result['errorType'] . ' ' . @$result['message'], $result['code']);
            }
            if (isset($result['message'])) {
                throw new \Exception($result['message'], $result['code']);
            }
            throw new \Exception('Unknown error', $result['code']);
        }

        return true;
    }
}
