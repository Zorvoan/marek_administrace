<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_cannot_be_framed_or_sniffed_and_have_a_strict_csp(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'")
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_error_pages_get_the_headers_too(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_hsts_is_sent_over_https_only(): void
    {
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_views_use_no_inline_scripts_or_styles(): void
    {
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($views as $file) {
            if ($file->isFile()) {
                $this->assertDoesNotMatchRegularExpression(
                    '/<script|<style|\sstyle=|\son[a-z]+=/i',
                    file_get_contents($file->getPathname()),
                    "{$file->getFilename()} would be blocked by the Content-Security-Policy"
                );
            }
        }
    }
}
