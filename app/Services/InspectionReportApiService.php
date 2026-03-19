<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InspectionReportApiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.inspection_api.url'), '/');
        $this->token   = config('services.inspection_api.token');
    }

    public function getDataReport(int $inspectionId): array
    {
        $url = "{$this->baseUrl}/inspection/report/{$inspectionId}";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url);

        if ($response->failed()) {

            return [
                'success' => false,
                'message' => 'inspection API error',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('inspection') 
                ?? $response->json('data') 
                ?? $response->json(),
        ];
    }

   /**
     * Generate PDF for inspection report
     * Asumsinya endpoint ini menggunakan method POST
     */
    public function postGeneratePDF(int $inspectionId): array
    {
        $url = "{$this->baseUrl}/inspection/report/{$inspectionId}/pdf";
        // Alternatif: $url = "{$this->baseUrl}/inspection/report/{$inspectionId}/pdf";

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(30) // PDF generation might take longer
                ->post($url); 

            if ($response->failed()) {
                Log::error('Inspection API Error (postGeneratePDF)', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to generate PDF',
                    'error'   => $response->json()
                ];
            }

            $responseData = $response->json();

            return [
                'success' => true,
                'data'    => $responseData['data'] ?? $responseData
            ];

        } catch (\Exception $e) {
            Log::error('Exception in InspectionReportApiService::postGeneratePDF', [
                'message' => $e->getMessage(),
                'inspection_id' => $inspectionId
            ]);

            return [
                'success' => false,
                'message' => 'Service exception: ' . $e->getMessage()
            ];
        }
    }
}
