<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Result;
use App\Services\EventDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventDeduplicationService;
    }

    public function test_it_finds_duplicate_events()
    {
        $date = now()->format('Y-m-d');

        $event1 = Event::create([
            'name' => 'Championnat de Suisse',
            'date' => $date,
            'location' => 'Sion',
        ]);

        $event2 = Event::create([
            'name' => 'CHAMPIONNAT DE SUISSE', // Same name, different case
            'date' => $date,
            'location' => 'Sion',
        ]);

        $event3 = Event::create([
            'name' => 'Ch. de Suisse', // Similar name
            'date' => $date,
            'location' => 'Sion',
        ]);

        $event4 = Event::create([
            'name' => 'Different Event',
            'date' => $date,
        ]);

        $clusters = $this->service->findDuplicates();

        $this->assertCount(1, $clusters);
        $this->assertCount(3, $clusters->first());

        $clusterIds = collect($clusters->first())->pluck('id')->toArray();
        $this->assertContains($event1->id, $clusterIds);
        $this->assertContains($event2->id, $clusterIds);
        $this->assertContains($event3->id, $clusterIds);
        $this->assertNotContains($event4->id, $clusterIds);
    }

    public function test_it_merges_events_and_transfers_results()
    {
        $primary = Event::create([
            'name' => 'Event A',
            'date' => now(),
            'location' => 'Sion',
        ]);

        $secondary = Event::create([
            'name' => 'Event A (Duplicate)',
            'date' => now(),
        ]);

        $result = Result::factory()->create([
            'event_id' => $secondary->id,
        ]);

        $this->service->mergeEvents($primary, $secondary);

        $this->assertModelMissing($secondary);
        $this->assertEquals($primary->id, $result->fresh()->event_id);

        // Check metadata merge
        $this->assertEquals('Sion', $primary->fresh()->location);
    }
}
