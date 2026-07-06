<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

/**
 * Lives outside Api\V1 and is registered in routes/web.php (no /api
 * prefix) so it's reachable at a conventional /sitemap.xml path on this
 * backend's own domain. In production, the frontend's actual domain needs
 * a reverse-proxy rule forwarding /sitemap.xml here — see
 * docs/legal-gdpr-seo.md.
 */
class SitemapController extends Controller
{
    public function __construct(private readonly SitemapService $sitemap) {}

    public function index(): Response
    {
        $xml = view('sitemap', ['entries' => $this->sitemap->entries()])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
