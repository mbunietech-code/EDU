<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_render_with_contents_and_links(): void
    {
        $pages = [
            '/privacy' => ['Privacy Policy', 'Information we collect', 'Personal Data Protection Act', 'AzamPay'],
            '/terms' => ['Terms of Service', 'Acceptable use', 'Refunds', 'United Republic of Tanzania'],
            '/account/delete' => ['Delete your account', 'Delete my account', 'Deleted within 30 days'],
        ];

        foreach ($pages as $url => $texts) {
            $response = $this->get($url)->assertOk();
            foreach ($texts as $text) {
                $response->assertSee($text, false);
            }
            // Every legal page links to the other two.
            $response->assertSee(route('public.terms'), false)
                ->assertSee(route('public.privacy'), false)
                ->assertSee(route('public.account-deletion'), false)
                // A fixed date, not "today".
                ->assertSee('Last updated 7 October 2026', false);

            if (($dump = env('LEGAL_DUMP_DIR')) !== null) {
                file_put_contents($dump.'/'.trim(str_replace('/', '-', $url), '-').'.html', $response->getContent());
            }
        }
    }
}
