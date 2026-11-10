<?php

namespace N2ns\Stats\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScriptController
{
    public function __invoke(Request $request): Response
    {
        $script = (string) file_get_contents(__DIR__.'/../../../resources/js/stats.js');

        $response = new Response($script, 200, ['Content-Type' => 'application/javascript; charset=utf-8']);
        $response->setEtag(sha1($script));
        $response->setPublic();
        $response->setMaxAge(86400);
        $response->isNotModified($request);

        return $response;
    }
}
