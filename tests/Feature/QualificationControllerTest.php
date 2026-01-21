<?php

use App\Models\AthleteCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed generic categories needed for logic
    AthleteCategory::factory()->create(['name' => 'U16M', 'genre' => 'M', 'age_limit' => 15]);
});

test('it can check qualifications via api with multiple files', function () {
    $limitsJson = json_encode([
        'years' => [2026],
        'disciplines' => [
            ['discipline' => '50m', 'categories' => ['U16M' => '10.00']],
        ],
    ]);

    $limitsFile = UploadedFile::fake()->createWithContent('limits.json', $limitsJson);

    $html = <<<'HTML'
<div class="listheader">
    <div class="leftheader">50m U16M</div>
    <div class="entryline">
       <div class="col-2"><div class="firstline">Api Runner</div></div>
       <div class="col-3"><div class="secondline">2011</div></div>
       <div class="col-4"><div class="firstline">9,00</div></div>
       <div class="col-4"><div class="firstline">U16M</div></div>
       <div class="col-last">CA Sion</div>
    </div>
</div>
HTML;
    $resultFile1 = UploadedFile::fake()->createWithContent('results1.html', $html);
    $resultFile2 = UploadedFile::fake()->createWithContent('results2.html', $html);

    $response = $this->postJson('/api/v1/qualifications/check', [
        'limits_file' => $limitsFile,
        'html_files' => [$resultFile1, $resultFile2],
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data',
        'stats' => ['raw_fetched', 'analyzed', 'qualified'],
    ]);

    // Deduplication should result in 1 athlete
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.athlete_name'))->toBe('Api Runner');
});

test('it can check qualifications via api with urls', function () {
    Http::fake([
        'example.com/*' => Http::response(<<<'HTML'
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
            , 200),
    ]);

    $limitsJson = json_encode([
        'years' => [2026],
        'disciplines' => [
            ['discipline' => '50m', 'categories' => ['U16M' => '10.00']],
        ],
    ]);
    $limitsFile = UploadedFile::fake()->createWithContent('limits.json', $limitsJson);

    $response = $this->postJson('/api/v1/qualifications/check', [
        'limits_file' => $limitsFile,
        'urls' => 'https://example.com/results',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.0.athlete_name'))->toBe('Url Runner');
});

test('it validates api inputs', function () {
    $response = $this->postJson('/api/v1/qualifications/check', []);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['limits_file']);
});
