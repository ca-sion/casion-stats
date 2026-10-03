<?php

namespace App\Livewire;

use App\Services\QualificationService;
use Livewire\Component;
use Livewire\WithFileUploads;

class CheckQualifications extends Component
{
    use WithFileUploads;

    public $limitsFile;

    public $sourceType = 'files'; // 'files' or 'urls'

    public $resultFiles = [];

    public $resultUrls = '';

    public $results = null;

    public $stats = null;

    public $errorMsg = null;

    public $isLoading = false;

    public function downloadExample($filename)
    {
        $path = base_path('resources/data/'.$filename);
        if (file_exists($path)) {
            return response()->download($path);
        }
        $this->errorMsg = "Fichier introuvable: $filename";
    }

    public function check(QualificationService $service)
    {
        $this->results = null;
        $this->errorMsg = null;
        $this->isLoading = true;

        $this->validate([
            'limitsFile' => 'required|file|mimes:json,txt', // max 1MB
            'resultFiles.*' => 'nullable|file', // max 5MB
            'resultUrls' => 'nullable|string',
        ]);

        try {
            // 1. Limits
            $limitsContent = '';
            if (is_object($this->limitsFile)) {
                if (method_exists($this->limitsFile, 'getRealPath') && is_file($this->limitsFile->getRealPath())) {
                    $limitsContent = file_get_contents($this->limitsFile->getRealPath());
                } elseif (method_exists($this->limitsFile, 'get')) {
                    $limitsContent = $this->limitsFile->get();
                }
            } elseif (is_string($this->limitsFile) && is_file($this->limitsFile)) {
                $limitsContent = file_get_contents($this->limitsFile);
            }

            $limitsJson = json_decode((string) $limitsContent, true);
            if (! is_array($limitsJson) || json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Le fichier de limites n'est pas un JSON valide.");
            }

            // 2. Results
            $filesToPass = [];
            $urlsToPass = [];

            if ($this->sourceType === 'files') {
                $filesToPass = $this->resultFiles;
            } else {
                $lines = explode("\n", $this->resultUrls);
                foreach ($lines as $url) {
                    $url = trim($url);
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        $urlsToPass[] = $url;
                    }
                }
            }

            // 3. Service Call
            $output = $service->check($limitsJson, $filesToPass, $urlsToPass);

            $this->results = $output['data'];
            $this->stats = $output['stats'];

        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
        } finally {
            $this->isLoading = false;
        }

    }

    public function render()
    {
        return view('livewire.check-qualifications');
    }
}
