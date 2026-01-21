<?php

use App\Models\Athlete;
use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Result;
use App\Services\QualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new QualificationService();
    
    // Seed generic categories needed for logic
    // We need simple U16M, U16W, etc.
    AthleteCategory::factory()->create(['name' => 'U16M', 'genre' => 'M', 'age_limit' => 15]);
    AthleteCategory::factory()->create(['name' => 'U16W', 'genre' => 'W', 'age_limit' => 15]);
    AthleteCategory::factory()->create(['name' => 'U18M', 'genre' => 'M', 'age_limit' => 17]);
    AthleteCategory::factory()->create(['name' => 'MAN', 'genre' => 'M', 'age_limit' => 99]);
});

test('it parses html content with complex structure regarding generic disciplines', function () {
    $html = <<<HTML
<div class="listheader">
    <div class="leftheader">
        <a href="#">50m haies U16 Hommes Séries</a>
    </div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Julen Malick</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">8,45</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div> <!-- Category Col -->
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;

    // We mock file_get_contents by writing to a temp file
    $file = UploadedFile::fake()->createWithContent('results.html', $html);

    $limits = [
        'years' => [2026],
        'disciplines' => [
            [
                'discipline' => '50mH (84.0)',
                'categories' => ['U16M' => '8.60']
            ]
        ]
    ];

    $results = $this->service->check($limits, [$file]);
    
    expect($results['data'])->toHaveCount(1);
    expect($results['data'][0]['athlete_name'])->toBe('Julen Malick');
    expect($results['data'][0]['performance_display'])->toBe('8.45');
    // Discipline should match generic header despite (84.0) difference
    expect($results['data'][0]['discipline_matched'])->toBe('50mH (84.0)');
    expect($results['data'][0]['limit_hit'])->toBe('8.60');
});

test('it handles database results correctly', function () {
    // seeded data
    $disc = Discipline::factory()->create(['name_fr' => '100 m', 'code' => '100m']);
    $cat = AthleteCategory::where('name', 'U16M')->first();
    $athlete = Athlete::factory()->create([
        'last_name' => 'Bolt', 
        'first_name' => 'Usbain', 
        'genre' => 'M', 
        'birthdate' => '2011-01-01'
    ]);
    
    $event = Event::factory()->create(['date' => '2026-06-01']);
    
    Result::factory()->create([
        'athlete_id' => $athlete->id,
        'discipline_id' => $disc->id,
        'event_id' => $event->id,
        'athlete_category_id' => $cat->id,
        'performance' => '10.50',
        'performance_normalized' => 10.50
    ]);

    $limits = [
        'years' => [2026],
        'disciplines' => [
            [
                'discipline' => '100m',
                'categories' => ['U16M' => '11.00']
            ]
        ]
    ];

    $results = $this->service->check($limits, []);
    
    expect($results['data'])->toHaveCount(1);
    expect($results['data'][0]['athlete_name'])->toContain('Bolt');
    expect($results['data'][0]['limit_hit'])->toBe('11.00');
});

test('it filters out unqualified results', function () {
    // HTML with slow result
    $html = <<<HTML
<div class="listheader">
    <div class="leftheader">100m U16M</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Slow Poke</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">15,00</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;
    $file = UploadedFile::fake()->createWithContent('slow.html', $html);

    $limits = [
        'years' => [2026],
        'disciplines' => [
            [
                'discipline' => '100m',
                'categories' => ['U16M' => '11.00']
            ]
        ]
    ];

    $results = $this->service->check($limits, [$file]);
    
    expect($results['data'])->toBeEmpty();
    expect($results['stats']['raw_fetched'])->toBe(1);
    expect($results['stats']['qualified'])->toBe(0);
});

test('it correctly identifies field events (greater than logic)', function () {
    $html = <<<HTML
<div class="listheader">
    <div class="leftheader">Longueur U16M</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Jumper Joe</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">6,00</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;
    $file = UploadedFile::fake()->createWithContent('jump.html', $html);

    $limits = [
        'years' => [2026],
        'disciplines' => [
            [
                'discipline' => 'Longueur',
                'categories' => ['U16M' => '5.50']
            ]
        ]
    ];

    $results = $this->service->check($limits, [$file]);
    
    expect($results['data'])->toHaveCount(1);
    expect($results['data'][0]['athlete_name'])->toBe('Jumper Joe');
    // 6.00 > 5.50 -> Qualified
});
