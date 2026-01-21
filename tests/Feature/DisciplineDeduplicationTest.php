<?php

namespace Tests\Feature;

use App\Models\Discipline;
use App\Models\Result;
use App\Services\DisciplineDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisciplineDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DisciplineDeduplicationService;
    }

    public function test_it_finds_duplicate_disciplines()
    {
        $d1 = Discipline::create([
            'name_fr' => '100 mètres',
            'name_de' => '100 Meter',
            'code' => '100m',
        ]);

        $d2 = Discipline::create([
            'name_fr' => '100m', // Similar
            'name_de' => '100m',
            'code' => '100',
        ]);

        $d3 = Discipline::create([
            'name_fr' => 'Hauteur',
            'name_de' => 'Hochsprung',
            'code' => 'HJ',
        ]);

        $clusters = $this->service->findDuplicates();

        $this->assertCount(1, $clusters);
        $this->assertCount(2, $clusters->first());

        $clusterIds = collect($clusters->first())->pluck('id')->toArray();
        $this->assertContains($d1->id, $clusterIds);
        $this->assertContains($d2->id, $clusterIds);
        $this->assertNotContains($d3->id, $clusterIds);
    }

    public function test_it_merges_disciplines_and_transfers_results()
    {
        $target = Discipline::create([
            'name_fr' => '100 mètres',
            'code' => '100m',
        ]);

        $source = Discipline::create([
            'name_fr' => '100m',
            'code' => '100',
        ]);

        $result = Result::factory()->create([
            'discipline_id' => $source->id,
        ]);

        $this->service->mergeDisciplines($target, [$source->id]);

        $this->assertModelMissing($source);
        $this->assertEquals($target->id, $result->fresh()->discipline_id);
    }
}
