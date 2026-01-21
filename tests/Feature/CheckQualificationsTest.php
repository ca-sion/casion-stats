<?php

use App\Livewire\CheckQualifications;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

test('it renders correctly', function () {
    Livewire::test(CheckQualifications::class)
        ->assertOk();
});

test('it validates required limits file', function () {
    Livewire::test(CheckQualifications::class)
        ->call('check')
        ->assertHasErrors(['limitsFile']);
});

test('it processes file uploads correctly', function () {
    $limitsJson = json_encode([
        'years' => [2026],
        'disciplines' => [
            ['discipline' => '50m', 'categories' => ['U16M' => '10.00']]
        ]
    ]);
    
    $limitsFile = UploadedFile::fake()->createWithContent('limits.json', $limitsJson);
    
    $html = <<<HTML
<div class="listheader">
    <div class="leftheader">50m U16M</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Flash Gordon</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">9,00</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;
    $resultFile = UploadedFile::fake()->createWithContent('results.html', $html);

    Livewire::test(CheckQualifications::class)
        ->set('limitsFile', $limitsFile)
        ->set('sourceType', 'files')
        ->set('resultFiles', [$resultFile])
        ->call('check')
        ->assertHasNoErrors()
        ->assertViewHas('errorMsg', null)
        ->assertViewHas('results', function ($results) {
            return count($results) === 1 && $results[0]['athlete_name'] === 'Flash Gordon';
        });
});

test('it processes urls correctly', function () {
    Http::fake([
        'example.com/*' => Http::response(<<<HTML
<div class="listheader">
    <div class="leftheader">50m U16M</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Url Runner</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">9,00</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML
        , 200)
    ]);

    $limitsJson = json_encode([
        'years' => [2026],
        'disciplines' => [
            ['discipline' => '50m', 'categories' => ['U16M' => '10.00']]
        ]
    ]);
    $limitsFile = UploadedFile::fake()->createWithContent('limits.json', $limitsJson);

    Livewire::test(CheckQualifications::class)
        ->set('limitsFile', $limitsFile)
        ->set('sourceType', 'urls')
        ->set('resultUrls', "https://example.com/results")
        ->call('check')
        ->assertHasNoErrors()
        ->assertViewHas('errorMsg', null)
        ->assertViewHas('results', function ($results) {
            return count($results) === 1 && $results[0]['athlete_name'] === 'Url Runner';
        });
});
