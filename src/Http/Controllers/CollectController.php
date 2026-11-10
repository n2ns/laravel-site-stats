<?php

namespace N2ns\Stats\Http\Controllers;

use Illuminate\Http\Request;
use N2ns\Stats\Collector\Collector;
use N2ns\Stats\Collector\Payload;
use Symfony\Component\HttpFoundation\Response;

/** POST api/event (design §5.1): 400 only for a malformed body, 204 for everything else, even rejected payloads. */
class CollectController
{
    public function __construct(private readonly Collector $collector) {}

    public function __invoke(Request $request): Response
    {
        $type = strtolower(trim(explode(';', (string) $request->headers->get('Content-Type'))[0]));
        if (! in_array($type, ['text/plain', 'application/json'], true)) {
            return new Response('', 400);
        }

        $payload = Payload::fromJson((string) $request->getContent());
        if ($payload === null) {
            return new Response('', 400);
        }

        $this->collector->collect($payload, $request);

        return new Response('', 204);
    }
}
