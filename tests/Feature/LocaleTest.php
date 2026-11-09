<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_stays_in_spanish_while_the_language_selector_is_disabled(): void
    {
        $this->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertDontSee('name="locale"', false);

        $this->assertSame('es', App::getLocale());
    }

    public function test_unsupported_languages_are_rejected(): void
    {
        $this->from(route('dashboard'))
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }
}
