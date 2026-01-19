<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MergeDisciplinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_merges_multiple_disciplines_into_one(): void
    {
        // 1. Setup
        $target = \App\Models\Discipline::factory()->create(['name_fr' => 'Target Discipline']);
        $source1 = \App\Models\Discipline::factory()->create(['name_fr' => 'Source 1']);
        $source2 = \App\Models\Discipline::factory()->create(['name_fr' => 'Source 2']);

        // Create results for each
        \App\Models\Result::factory()->count(2)->create(['discipline_id' => $source1->id]);
        \App\Models\Result::factory()->count(3)->create(['discipline_id' => $source2->id]);
        \App\Models\Result::factory()->count(1)->create(['discipline_id' => $target->id]);

        // 2. Execute
        $this->artisan("app:merge-disciplines --from={$source1->id},{$source2->id} --to={$target->id}")
            ->expectsOutput("Merging 2 disciplines into 'Target Discipline' (ID: {$target->id})")
            ->expectsConfirmation('Do you wish to continue?', 'yes')
            ->expectsOutput("Reassigned 5 results.")
            ->expectsOutput("Deleted 2 source disciplines.")
            ->assertExitCode(0);

        // 3. Verify
        $this->assertEquals(6, \App\Models\Result::where('discipline_id', $target->id)->count());
        $this->assertDatabaseMissing('disciplines', ['id' => $source1->id]);
        $this->assertDatabaseMissing('disciplines', ['id' => $source2->id]);
        $this->assertDatabaseHas('disciplines', ['id' => $target->id]);
    }

    public function test_it_fails_if_target_not_found(): void
    {
        $this->artisan('app:merge-disciplines --from=1 --to=999')
            ->expectsOutput('Target discipline with ID 999 not found.')
            ->assertExitCode(1);
    }
}
