<?php

namespace App\Services;

use App\Models\Discipline;
use App\Models\AthleteCategory;
use App\Models\Result;
use GlaivePro\IaafPoints\IaafCalculator;

class IaafPointsService
{
    /**
     * Map internal discipline codes/WA codes to IAAF keys.
     */
    protected array $mapping = [
        '100' => '100m',
        '200' => '200m',
        '400' => '400m',
        '800' => '800m',
        '1500' => '1500m',
        '5000' => '5000m',
        '10000' => '10000m',
        '50' => '50m',
        '60' => '60m',
        '110H' => '110mh',
        '100H' => '100mh',
        '400H' => '400mh',
        '60H' => '60mh',
        '50H' => '50mh',
        '3KSC' => '3000mSt',
        '2KSC' => '2000mSt',
        'HJ' => 'high_jump',
        'PV' => 'pole_vault',
        'LJ' => 'long_jump',
        'TJ' => 'triple_jump',
        'SP' => 'shot_put',
        'DT' => 'discus_throw',
        'HT' => 'hammer_throw',
        'JT' => 'javelin_throw',
        'DEC' => 'decathlon',
        'HEP' => 'heptathlon',
        // Add more mappings as needed
    ];

    /**
     * Cache for IAAF calculators to avoid repeated instantiation.
     */
    protected static array $calculators = [];

    /**
     * Calculate IAAF points for a given result.
     */
    public function getPoints(Result $result): int
    {
        $discipline = $result->discipline;
        if ($result->isDirty('discipline_id') || !$discipline) {
            $discipline = Discipline::find($result->discipline_id);
        }

        $category = $result->athleteCategory;
        if ($result->isDirty('athlete_category_id') || !$category) {
            $category = AthleteCategory::find($result->athlete_category_id);
        }

        $iaafKey = $this->getIaafKey($discipline);
        if (!$iaafKey) {
            return 0;
        }

        $gender = $category?->genre ?? 'm';
        if ($gender === 'w') {
            $gender = 'f';
        }
        
        $venue = $this->getVenue($discipline);

        $calcKey = "{$iaafKey}_{$gender}_{$venue}";

        if (!isset(static::$calculators[$calcKey])) {
            static::$calculators[$calcKey] = new IaafCalculator([
                'discipline' => $iaafKey,
                'gender' => $gender,
                'venueType' => $venue,
            ]);
        }

        return (int) static::$calculators[$calcKey]->evaluate($result->performance_normalized);
    }

    /**
     * Get the IAAF discipline key for a given Discipline model.
     */
    public function getIaafKey(Discipline $discipline): ?string
    {
        // 1. Try mapping the wa_code
        if ($discipline->wa_code && isset($this->mapping[$discipline->wa_code])) {
            return $this->mapping[$discipline->wa_code];
        }

        // 2. Try mapping the internal code
        if ($discipline->code && isset($this->mapping[$discipline->code])) {
            return $this->mapping[$discipline->code];
        }

        // 3. Fallback to some common transformations if no explicit mapping
        $name = strtolower($discipline->name_fr);
        
        if (str_contains($name, '100 m') && !str_contains($name, 'haies')) return '100m';
        if (str_contains($name, '200 m') && !str_contains($name, 'haies')) return '200m';
        if (str_contains($name, '400 m') && !str_contains($name, 'haies')) return '400m';
        if (str_contains($name, '800 m')) return '800m';
        if (str_contains($name, '1500 m')) return '1500m';
        if (str_contains($name, '5000 m')) return '5000m';
        if (str_contains($name, '10000 m')) return '10000m';
        if (preg_match('/^50 ?m/', $name) && !str_contains($name, 'haies')) return '50m';
        if (preg_match('/^60 ?m/', $name) && !str_contains($name, 'haies')) return '60m';
        if (str_contains($name, '50 m haies')) return '50mh';
        if (str_contains($name, '60 m haies')) return '60mh';
        
        // Field events
        if (str_contains($name, 'hauteur')) return 'high_jump';
        if (str_contains($name, 'perche')) return 'pole_vault';
        if (str_contains($name, 'longueur')) return 'long_jump';
        if (str_contains($name, 'triple')) return 'triple_jump';
        if (str_contains($name, 'poids')) return 'shot_put';
        if (str_contains($name, 'disque')) return 'discus_throw';
        if (str_contains($name, 'marteau')) return 'hammer_throw';
        if (str_contains($name, 'javelot')) return 'javelin_throw';

        return null;
    }

    /**
     * Determine the venue type (indoor/outdoor) based on discipline.
     */
    protected function getVenue(Discipline $discipline): string
    {
        $key = $this->getIaafKey($discipline);

        if (in_array($key, ['50m', '60m', '50mh', '60mh'])) {
            return 'indoor';
        }

        return 'outdoor';
    }
}
