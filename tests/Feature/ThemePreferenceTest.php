<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_layout_exposes_theme_preference_and_dark_mode_support(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('theme-preference', false);
        $response->assertSee('Tema visual', false);

        $javascript = File::get(resource_path('js/app.js'));
        $stylesheet = File::get(resource_path('css/app.css'));
        $this->assertStringContainsString('rincon-ganadero-theme', $javascript);
        $layout = File::get(base_path('resources/views/layouts/app.blade.php'));
        $this->assertStringContainsString("? stored : 'light'", $layout);
        $this->assertStringContainsString('data-theme-choice="light"', $layout);
        $this->assertStringContainsString('data-theme-choice="dark"', $layout);
        $this->assertStringContainsString('prefers-color-scheme: dark', $javascript);
        $this->assertStringContainsString('html[data-theme="dark"]', $stylesheet);
        $this->assertStringContainsString('color-scheme: dark', $stylesheet);
    }
}
