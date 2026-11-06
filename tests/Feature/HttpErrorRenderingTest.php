<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpErrorRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_web_page_keeps_404_status_and_shows_error_alert(): void
    {
        $this->get('/missing-page')
            ->assertNotFound()
            ->assertSee('data-swal-error', false)
            ->assertSee('No encontramos el registro o la página que buscas.');
    }
}
