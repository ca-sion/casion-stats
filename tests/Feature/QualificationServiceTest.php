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

test('it handles global_limit when category is unknown', function () {
    $limits = [
        'years' => [2026],
        'disciplines' => [
            [
                'discipline' => 'Longueur',
                'global_limit' => '6.00'
            ]
        ]
    ];

    $html = <<<HTML
<div class="listheader">
    <div class="leftheader">Longueur</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Global Jumper</div></div>
       <div class="col-3"><div class="secondline">2010</div></div>
       <div class="col-4"><div class="firstline">6.10</div></div>
       <div class="col-4"><div class="firstline">UNKNOWN_CAT</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;

    $service = new \App\Services\QualificationService();
    $output = $service->check($limits, [], [], 'CA Sion', [$html]);
    
    expect($output['stats']['qualified'])->toBe(1);
    expect($output['data'][0]['category_hit'])->toBe('Global');
    expect($output['data'][0]['athlete_name'])->toBe('Global Jumper');
});

test('it identifies near misses within 5% margin', function () {
    $limits = [
        'years' => [2026],
        'disciplines' => [
            ['discipline' => '100m', 'categories' => ['U16M' => '10.00']], // Track: max 10.50
            ['discipline' => 'Longueur', 'categories' => ['U16M' => '6.00']] // Field: min 5.70
        ]
    ];

    $html = <<<HTML
<div class="listheader">
    <div class="leftheader">100m</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Slow Runner</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">10.30</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
<div class="listheader">
    <div class="leftheader">Longueur</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Short Jumper</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">5.80</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;

    $service = new \App\Services\QualificationService();
    $output = $service->check($limits, [], [], 'CA Sion', [$html]);
    
    expect($output['stats']['qualified'])->toBe(0);
    expect($output['stats']['near_miss'])->toBe(2);
    
    $results = collect($output['data']);
    expect($results->where('athlete_name', 'Slow Runner')->first()['status'])->toBe('near_miss');
    expect($results->where('athlete_name', 'Short Jumper')->first()['status'])->toBe('near_miss');
});

test('it handles gender-specific global limits', function () {
    $limits = [
        'years' => [2026],
        'disciplines' => [
            [
                'discipline' => '100m',
                'global_M' => '11.00',
                'global_W' => '12.50'
            ]
        ]
    ];

    $html = <<<HTML
<div class="listheader">
    <div class="leftheader">100m</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Male Sprinter</div></div>
       <div class="col-3"><div class="secondline">2005</div></div>
       <div class="col-4"><div class="firstline">10.90</div></div>
       <div class="col-4"><div class="firstline">MAN</div></div>
       <div class="col-last">CA Sion</div>
    </div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Female Sprinter</div></div>
       <div class="col-3"><div class="secondline">2005</div></div>
       <div class="col-4"><div class="firstline">12.40</div></div>
       <div class="col-4"><div class="firstline">WOM</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;

    $service = new \App\Services\QualificationService();
    $output = $service->check($limits, [], [], 'CA Sion', [$html]);
    
    expect($output['stats']['qualified'])->toBe(2);
    
    $results = collect($output['data']);
    expect($results->where('athlete_name', 'Male Sprinter')->first()['category_hit'])->toBe('Global M');
    expect($results->where('athlete_name', 'Female Sprinter')->first()['category_hit'])->toBe('Global W');
});

test('it handles secondary limits (qualifies_for)', function () {
    $limits = [
        'years' => [2026],
        'disciplines' => [
            [
                'discipline' => '50m',
                'categories' => ['U16M' => '7.00'],
                'qualifies_for' => ['60m']
            ],
            [
                'discipline' => '60m',
                'categories' => ['U16M' => '8.00']
            ]
        ]
    ];

    $html = <<<HTML
<div class="listheader">
    <div class="leftheader">50m</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Indirect Runner</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">6.90</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
<div class="listheader">
    <div class="leftheader">60m</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Indirect Runner</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">8.50</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;

    $service = new \App\Services\QualificationService();
    $output = $service->check($limits, [], [], 'CA Sion', [$html]);
    
    // Should have 2 entries in data: 1 for 50m, 1 for 60m
    expect(count($output['data']))->toBe(2);
    
    $results = collect($output['data']);
    expect($results->where('discipline_name', '50m')->first())->not->toBeNull();
    $runner60 = $results->where('discipline_name', '60m')->first();
    expect($runner60['via_secondary'])->toBe('50m');
    expect($runner60['secondary_perf'])->toBe('6.90');
    expect($runner60['secondary_limit'])->toBe('7.00'); 
    expect($runner60['primary_limit'])->toBe('8.00'); 
    expect($runner60['primary_performance_display'])->toBe('8.50');
});

test('it handles multiple qualification paths for the same discipline', function () {
    $limits = [
        'years' => [2026],
        'disciplines' => [
            [
                'discipline' => '50m',
                'categories' => ['U16M' => '7.00'],
                'qualifies_for' => ['60m']
            ],
            [
                'discipline' => '60m',
                'categories' => ['U16M' => '8.00']
            ]
        ]
    ];

    $html = <<<HTML
<div class="listheader">
    <div class="leftheader">50m</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Flash Gordon</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">6.50</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
<div class="listheader">
    <div class="leftheader">60m</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Flash Gordon</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">7.80</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;

    $service = new \App\Services\QualificationService();
    $output = $service->check($limits, [], [], 'CA Sion', [$html]);
    
    // Should have 3 entries: 
    // 1. 50m (Direct)
    // 2. 60m (Direct)
    // 3. 60m (Via 50m)
    expect(count($output['data']))->toBe(3);
    
    $results = collect($output['data']);
    expect($results->where('discipline_name', '50m')->count())->toBe(1);
    
    $sixtyResults = $results->where('discipline_name', '60m');
    expect($sixtyResults->count())->toBe(2);
    expect($sixtyResults->where('via_secondary', null)->count())->toBe(1);
    expect($sixtyResults->where('via_secondary', '50m')->count())->toBe(1);
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
