<?php

namespace App\Http\Middleware;

use App\Support\Http\DeferredWrites;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Writes whatever DeferredWrites collected once the response is out. First in the global
// stack, so it sees the response last on the way out, whether or not a route matched.
class FlushDeferredWrites
{
    public function __construct(private DeferredWrites $writes)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // php -S answers with Connection: close and no length, so the browser can only tell
        // the page has ended when PHP exits, which is after terminate() and these writes.
        // A length lets it finish reading first. Streams and files are left as they are.
        if ($this->writes->pending()
            && ! $response instanceof StreamedResponse
            && ! $response instanceof BinaryFileResponse
            && ! $response->headers->has('Content-Length')
            && ! $response->headers->has('Transfer-Encoding')) {
            $content = $response->getContent();
            if (is_string($content)) {
                $response->headers->set('Content-Length', (string) strlen($content));
            }
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->writes->flush();
    }
}
