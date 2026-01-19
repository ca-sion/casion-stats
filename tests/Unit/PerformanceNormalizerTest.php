<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Support\PerformanceNormalizer;

class PerformanceNormalizerTest extends TestCase
{
    // Use the trait in an anonymous class or directly if possible, 
    // but easier to just bind it to a class for testing.
    private $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->normalizer = new class {
            use PerformanceNormalizer;
        };
    }

    /**
     * @dataProvider performanceProvider
     */
    public function test_parses_performance_correctly($input, $expected)
    {
        $result = $this->normalizer->parsePerformanceToSeconds($input);
        
        // Use delta for float comparison to avoid precision issues
        if ($expected === null) {
            $this->assertNull($result);
        } else {
            $this->assertEqualsWithDelta($expected, $result, 0.001, "Failed parsing: $input");
        }
    }

    public static function performanceProvider()
    {
        return [
            // Standard Seconds
            ['10.50', 10.50],
            ['9.58', 9.58],
            
            // Minutes:Seconds
            ['3:03.02', 183.02], // 3*60 + 3.02
            ['2:00.00', 120.00],
            ['0:50.00', 50.00],
            
            // Hours:Minutes:Seconds
            ['1:00:00', 3600.0],
            ['2:30:30', 9030.0], // 2*3600 + 30*60 + 30
            
            // "h" format
            ['0h30:08.00', 1808.0], // 30*60 + 8
            ['1h00:00', 3600.0],
            
            // Dot normalization (fixing weird formats)
            ['14..13', 14.13],
            ['2.54.47', 174.47], // Treated as 2:54.47 -> 120 + 54.47
            
            // Long distance simple numbers (e.g. Heptathlon points or raw seconds?)
            // The logic says "numeric string" -> float. 
            // '4448' -> 4448.0.
            ['4448', 4448.0],
            
            // Cleanup metadata
            ['10.50 : 200', 10.50],
            ['10.50 - 200', 10.50],
            
            // Invalid / Empty
            ['', null],
            ['DNS', null],
            ['DQ', null],
            ['NM', null],
        ];
    }
}
