<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ResponsiveLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_styles_define_responsive_breakpoints_and_overflow_protection(): void
    {
        $stylesheet = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('@media (max-width: 1200px)', $stylesheet);
        $this->assertStringContainsString('@media (max-width: 820px)', $stylesheet);
        $this->assertStringContainsString('@media (max-width: 560px)', $stylesheet);
        $this->assertStringContainsString('@media (max-width: 380px)', $stylesheet);
        $this->assertStringContainsString('.table-wrap { max-width: 100%', $stylesheet);
        $this->assertStringContainsString('.main-content { min-width: 0; overflow-x: hidden', $stylesheet);
        $this->assertStringContainsString('.calendar-main { overflow-x: auto', $stylesheet);
        $this->assertStringContainsString('.sidebar { position: fixed;', $stylesheet);
        $this->assertStringContainsString('.workspace { min-height: 100vh; margin-left: 248px;', $stylesheet);

        $layout = File::get(base_path('resources/views/layouts/app.blade.php'));
        $this->assertStringContainsString('class="main-nav"', $layout);
        $this->assertStringContainsString('Fincas y movimientos', $layout);
        $this->assertStringContainsString('Administración', $layout);
        $this->assertStringContainsString('data-app-loading', $layout);

        $javascript = File::get(resource_path('js/app.js'));
        $this->assertStringContainsString('hideAppLoading', $javascript);
        $this->assertStringContainsString('.app-loading.is-ready', $stylesheet);
        $this->assertStringContainsString('*::-webkit-scrollbar-thumb', $stylesheet);
        $this->assertStringContainsString('scrollbar-color: #3f8b65 #e7efe8', $stylesheet);
        $this->assertStringContainsString('.calendar-sidebar { position: sticky;', $stylesheet);
        $this->assertStringContainsString('@media (max-width: 820px)', $stylesheet);
        $this->assertStringContainsString('.action-menu-content', $stylesheet);
        $animalsView = File::get(resource_path('views/animals/index.blade.php'));
        $this->assertStringContainsString('class="action-menu"', $animalsView);
        $this->assertStringContainsString('class="animal-import-panel"', $animalsView);
    }
}
