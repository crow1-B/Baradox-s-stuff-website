<?php

// Read by Laravel's TrustProxies middleware. Only a proxy listed here may set the client IP
// (and scheme, host, port) through X-Forwarded-* headers; from anyone else those headers are
// ignored and $request->ip() is the socket's address.
//
// TRUSTED_PROXIES is a comma-separated list of the reverse proxy's own addresses or CIDR
// ranges, as this app sees them (for a proxy container on the same Docker network, its
// address or that network's subnet). Unset means no proxy is trusted. The default is an empty
// array on purpose, never null: with null the middleware trusts every address when the Host
// header ends in .on-forge.com or .on-vapor.com, and the client chooses the Host header.
// Avoid '*': it trusts whatever connects, which is the spoofing this setting exists to stop.

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'proxies' => match (true) {
        $proxies === '' => [],
        $proxies === '*', $proxies === '**' => $proxies,
        default => array_values(array_filter(array_map('trim', explode(',', $proxies)))),
    },
];
