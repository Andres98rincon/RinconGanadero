<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_cleanup_removes_records_older_than_thirty_days_only(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        $oldActivity = Activity::query()->create([
            'log_name' => 'animals',
            'description' => 'Cambio antiguo',
            'event' => 'updated',
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);
        $recentActivity = Activity::query()->create([
            'log_name' => 'animals',
            'description' => 'Cambio reciente',
            'event' => 'updated',
            'created_at' => now()->subDays(29),
            'updated_at' => now()->subDays(29),
        ]);

        $this->artisan('activitylog:clean', ['--force' => true])->assertExitCode(0);

        $this->assertDatabaseMissing('activity_log', ['id' => $oldActivity->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $recentActivity->id]);
    }
}
