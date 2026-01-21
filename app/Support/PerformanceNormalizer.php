<?php

namespace App\Support;

trait PerformanceNormalizer
{
    /**
     * Parse various performance formats into a total number of seconds (float).
     * Supported: 0h30:08.00, 3:03.02, 10.50, 4448 (as number string)
     */
    public function parsePerformanceToSeconds(string $performance): ?float
    {
        $performance = trim($performance);
        if (empty($performance)) {
            return null;
        }

        // Skip non-performance strings (must contain at least one digit)
        if (! preg_match('/\d/', $performance)) {
            return null;
        }

        // Strip metadata suffixes like " : 200" or "-200" (often used for indoor track length)
        // Allow optional spaces around the separator (: or -)
        $performance = preg_replace('/(?:\s*[:\-]\s*)(?:200|400)?\s*$/', '', $performance);
        $performance = trim($performance);

        // Normalize multiple dots: 14..13 -> 14.13
        $performance = preg_replace('/\.+/', '.', $performance);

        // Normalize dots if they are used as delimiters: 2.54.47 -> 2:54.47
        // If we have no colons but multiple dots, we replace all but the last dot with colons
        if (! str_contains($performance, ':') && substr_count($performance, '.') >= 2) {
            $lastDotPos = strrpos($performance, '.');
            $preLastDot = substr($performance, 0, $lastDotPos);
            $performance = str_replace('.', ':', $preLastDot).substr($performance, $lastDotPos);
        }

        // Handle format with "h": 0h30:08.00
        if (str_contains($performance, 'h')) {
            if (preg_match('/(?:(\d+)h)?(?:(\d+):)?(\d+)(?:\.(\d+))?/', $performance, $matches)) {
                $hours = (int) ($matches[1] ?? 0);
                $minutes = (int) ($matches[2] ?? 0);
                $seconds = (int) ($matches[3] ?? 0);
                $ms = isset($matches[4]) ? (float) ('0.'.$matches[4]) : 0;

                return ($hours * 3600) + ($minutes * 60) + $seconds + $ms;
            }
        }

        // Handle format with ":": 3:03.02 or 10:30
        if (str_contains($performance, ':')) {
            $parts = explode(':', $performance);
            if (count($parts) === 2) {
                $minutes = (int) $parts[0];
                $seconds = (float) $parts[1];

                return ($minutes * 60) + $seconds;
            }
            if (count($parts) === 3) { // H:M:S
                $hours = (int) $parts[0];
                $minutes = (int) $parts[1];
                $seconds = (float) $parts[2];

                return ($hours * 3600) + ($minutes * 60) + $seconds;
            }
        }

        // Handle simple numeric: 10.50 or 4448
        $cleanValue = preg_replace('/[^0-9.]/', '', $performance);

        return is_numeric($cleanValue) ? (float) $cleanValue : null;
    }
}
