<?php

declare(strict_types=1);

namespace Boneradf\Security;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Contracts\Middleware;

/**
 * Security headers middleware — ON by default (D5).
 *
 * Emits five headers on every response:
 *   Content-Security-Policy, Strict-Transport-Security,
 *   X-Frame-Options, X-Content-Type-Options, Referrer-Policy
 */
final class SecurityHeaders implements Middleware
{
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $overrides  header-name => value
     */
    public function __construct(array $overrides = [])
    {
        $this->headers = array_merge([
            'Content-Security-Policy'  => "default-src 'none'; frame-ancestors 'none'",
            'Strict-Transport-Security' => 'max-age=63072000; includeSubDomains; preload',
            'X-Frame-Options'          => 'DENY',
            'X-Content-Type-Options'   => 'nosniff',
            'Referrer-Policy'          => 'no-referrer',
        ], $overrides);
    }

    public function handle(Request $req, callable $next): Response
    {
        $resp = $next($req);
        foreach ($this->headers as $name => $value) {
            $resp = $resp->withHeader($name, $value);
        }
        return $resp;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
