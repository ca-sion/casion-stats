<?php

namespace App\Services;

use App\Models\Discipline;
use App\Models\Result;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DisciplineDeduplicationService
{
    /**
     * Find potential duplicate disciplines.
     * Returns a collection of clusters (arrays of Disciplines).
     */
    public function findDuplicates(): Collection
    {
        $disciplines = Discipline::all();
        $processedIds = [];
        $clusters = [];
        $count = $disciplines->count();

        for ($i = 0; $i < $count; $i++) {
            $a = $disciplines[$i];
            if (in_array($a->id, $processedIds)) continue;

            $cluster = [$a];
            $hasDuplicates = false;

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $disciplines[$j];
                if (in_array($b->id, $processedIds)) continue;

                if ($this->arePotentialDuplicates($a, $b)) {
                    $cluster[] = $b;
                    $processedIds[] = $b->id;
                    $hasDuplicates = true;
                }
            }

            if ($hasDuplicates) {
                $clusters[] = $cluster;
                $processedIds[] = $a->id;
            }
        }

        return collect($clusters);
    }

    /**
     * Determine if two disciplines are potential duplicates.
     */
    public function arePotentialDuplicates(Discipline $a, Discipline $b): bool
    {
        $nameA_fr = $this->normalize($a->name_fr ?? '');
        $nameB_fr = $this->normalize($b->name_fr ?? '');
        $nameA_de = $this->normalize($a->name_de ?? '');
        $nameB_de = $this->normalize($b->name_de ?? '');

        // 1. Exact Match on normalized names
        if (($nameA_fr !== '' && $nameA_fr === $nameB_fr) || 
            ($nameA_de !== '' && $nameA_de === $nameB_de)) {
            return true;
        }

        // 2. Cross-language match (sometimes people put DE name in FR column or vice versa)
        if (($nameA_fr !== '' && ($nameA_fr === $nameB_de)) || 
            ($nameA_de !== '' && ($nameA_de === $nameB_fr))) {
            return true;
        }

        // 3. Fuzzy Match on FR name
        if ($nameA_fr !== '' && $nameB_fr !== '') {
            $percent = 0;
            similar_text($nameA_fr, $nameB_fr, $percent);
            if ($percent > 80) return true;
            
            // Special case for short names like "100m" vs "100 metres"
            if (str_starts_with($nameA_fr, $nameB_fr) || str_starts_with($nameB_fr, $nameA_fr)) {
                if (strlen($nameA_fr) > 2 && strlen($nameB_fr) > 2) return true;
            }
        }

        return false;
    }

    private function normalize(string $string): string
    {
        $string = strtolower($string);
        // Replace common abbreviations/terms
        $string = str_replace(['mètres', 'meter', 'mètre'], 'm', $string);
        $string = str_replace(' ', '', $string);
        // Remove accents
        $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        // Remove non-alphanumeric
        $string = preg_replace('/[^a-z0-9]/', '', $string);
        
        return $string;
    }

    /**
     * Merge multiple source disciplines into a target discipline.
     */
    public function mergeDisciplines(Discipline $target, array $sourceIds): void
    {
        DB::transaction(function () use ($target, $sourceIds) {
            // Reassign results
            Result::whereIn('discipline_id', $sourceIds)
                ->update(['discipline_id' => $target->id]);

            // Delete sources
            Discipline::whereIn('id', $sourceIds)->delete();
        });
    }
}
