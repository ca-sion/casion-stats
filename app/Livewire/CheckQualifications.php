<?php

namespace App\Livewire;

use App\Services\QualificationService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Http;

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
            $limitsContent = file_get_contents($this->limitsFile->getRealPath());
            $limitsJson = json_decode($limitsContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
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
