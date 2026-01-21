<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\QualificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QualificationController extends Controller
{
    public function __construct(protected QualificationService $service) {}

    /**
     * Check qualifications based on limits file and optional result sources.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'limits_file' => 'required|file|mimes:json,txt', // .json sometimes detected as text
            'html_files.*' => 'nullable|file', // HTML files
            'urls' => 'nullable|string', // Text area with URLs
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 1. Process Limits JSON
        try {
            $limitsContent = $request->file('limits_file')->get();
            $limitsJson = json_decode($limitsContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format');
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error parsing limits file: '.$e->getMessage()], 422);
        }

        // 2. Gather HTML Content
        $htmlContents = [];

        // From Files
        if ($request->hasFile('html_files')) {
            foreach ($request->file('html_files') as $file) {
                $htmlContents[] = $file->get();
            }
        }

        // From URLs
        $urlsToPass = [];
        if ($request->filled('urls')) {
            $urlsToPass = explode("\n", $request->input('urls'));
            $urlsToPass = array_filter(array_map('trim', $urlsToPass), function ($url) {
                return filter_var($url, FILTER_VALIDATE_URL);
            });
        }

        // 3. Service Call
        try {
            // Note: Controller passes raw HTML contents as strings (4th param) and URLs (3rd param)
            $result = $this->service->check($limitsJson, [], array_values($urlsToPass), 'CA Sion', $htmlContents);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error during verification: '.$e->getMessage()], 500);
        }
    }
}
