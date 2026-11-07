<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_between_supported_languages(): void
    {
        $this->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Language', false)
            ->assertSee('Dashboard', false)
            ->assertSee('Your farm, at a glance.', false);

        $this->assertSame('en', App::getLocale());
    }

    public function test_unsupported_languages_are_rejected(): void
    {
        $this->from(route('dashboard'))
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }
}
