<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\IaafPointsService;
use App\Models\Discipline;
use App\Models\Result;
use App\Models\AthleteCategory;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class IaafPointsServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IaafPointsService();
    }

    /**
     * @dataProvider mappingProvider
     */
    public function test_maps_disciplines_correctly($waCode, $nameFr, $expectedKey)
    {
        $discipline = Mockery::mock(Discipline::class);
        $discipline->shouldReceive('getAttribute')->with('wa_code')->andReturn($waCode);
        $discipline->shouldReceive('getAttribute')->with('code')->andReturn(null);
        $discipline->shouldReceive('getAttribute')->with('name_fr')->andReturn($nameFr);

        $this->assertEquals($expectedKey, $this->service->getIaafKey($discipline));
    }

    public static function mappingProvider()
    {
        return [
            ['100', '100 m', '100m'],
            ['800', '800 m', '800m'],
            ['110H', '110 m haies', '110mh'],
            ['3KSC', '3000 m Steeple', '3000mSt'],
            ['HJ', 'Hauteur', 'high_jump'],
            ['PV', 'Perche', 'pole_vault'],
            [null, '1500 m', '1500m'],
            [null, 'Longueur', 'long_jump'],
            [null, 'Inconnu', null],
        ];
    }

    public function test_calculates_points_correctly()
    {
        // 100m Men, 10.00s should be ~1096 points (2022 table)
        
        $discipline = Mockery::mock(Discipline::class);
        $discipline->shouldReceive('getAttribute')->with('wa_code')->andReturn('100');
        $discipline->shouldReceive('getAttribute')->with('code')->andReturn(null);
        $discipline->shouldReceive('getAttribute')->with('name_fr')->andReturn('100 m');
        $discipline->shouldReceive('offsetExists')->andReturn(true);

        $category = Mockery::mock(AthleteCategory::class);
        $category->shouldReceive('getAttribute')->with('genre')->andReturn('m');
        $category->shouldReceive('offsetExists')->andReturn(true);

        $result = Mockery::mock(Result::class);
        
        // Mocking the attributes accessed in the service
        $result->shouldReceive('getAttribute')->with('discipline')->andReturn($discipline);
        $result->shouldReceive('getAttribute')->with('athleteCategory')->andReturn($category);
        $result->shouldReceive('getAttribute')->with('performance_normalized')->andReturn(10.00);
        
        // Mocking offsetExists if called by Laravel/Mockery
        $result->shouldReceive('offsetExists')->andReturn(true);

        $points = $this->service->getPoints($result);
        
        $this->assertEquals(1206, $points);
    }
}
