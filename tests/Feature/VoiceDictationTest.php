<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceDictationTest extends TestCase
{
    use RefreshDatabase;

    public function test_voice_dictation_is_loaded_for_textareas_and_manual_fallback_is_defined(): void
    {
        $javascript = File::get(resource_path('js/app.js'));
        $stylesheet = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('SpeechRecognition', $javascript);
        $this->assertStringContainsString('webkitSpeechRecognition', $javascript);
        $this->assertStringContainsString('voiceDictation', $javascript);
        $this->assertStringContainsString('Dictado no disponible', $javascript);
        $this->assertStringContainsString('voice-input-wrapper', $stylesheet);
        $this->assertStringContainsString('voice-pulse', $stylesheet);
    }

    public function test_finance_form_keeps_an_editable_observations_textarea_for_dictation(): void
    {
        $response = $this->get(route('finance.transactions.create'));

        $response->assertOk();
        $response->assertSee('id="notes"', false);
        $response->assertSee('name="notes"', false);
    }
}
