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

    
    /* =====================
     * ESTIMASI — STORE
     * ===================== */
 
    /**
     * POST /inspection/report/{inspectionId}/estimasi
     */
    public function storeEstimasi(int $inspectionId, array $payload): array
    {
        $url = "{$this->baseUrl}/inspection/report/{$inspectionId}/estimasi";
 
        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(15)
                ->post($url, $payload);
 
            if ($response->failed()) {
                Log::error('Inspection API Error (storeEstimasi)', [
                    'status'        => $response->status(),
                    'body'          => $response->body(),
                    'inspection_id' => $inspectionId,
                ]);
 
                return [
                    'success' => false,
                    'message' => 'Failed to create estimasi',
                    'error'   => $response->json(),
                ];
            }
 
            return [
                'success' => true,
                'data'    => $response->json('data') ?? $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Exception in InspectionReportApiService::storeEstimasi', [
                'message'       => $e->getMessage(),
                'inspection_id' => $inspectionId,
            ]);
 
            return [
                'success' => false,
                'message' => 'Service exception: ' . $e->getMessage(),
            ];
        }
    }
 
    /* =====================
     * ESTIMASI — UPDATE
     * ===================== */
 
    /**
     * PUT /inspection/report/{inspectionId}/estimasi/{estimasiId}
     */
    public function updateEstimasi(int $inspectionId, int $estimasiId, array $payload): array
    {
        $url = "{$this->baseUrl}/inspection/report/{$inspectionId}/estimasi/{$estimasiId}";
 
        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(15)
                ->put($url, $payload);
 
            if ($response->failed()) {
                Log::error('Inspection API Error (updateEstimasi)', [
                    'status'        => $response->status(),
                    'body'          => $response->body(),
                    'inspection_id' => $inspectionId,
                    'estimasi_id'   => $estimasiId,
                ]);
 
                return [
                    'success' => false,
                    'message' => 'Failed to update estimasi',
                    'error'   => $response->json(),
                ];
            }
 
            return [
                'success' => true,
                'data'    => $response->json('data') ?? $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Exception in InspectionReportApiService::updateEstimasi', [
                'message'       => $e->getMessage(),
                'inspection_id' => $inspectionId,
                'estimasi_id'   => $estimasiId,
            ]);
 
            return [
                'success' => false,
                'message' => 'Service exception: ' . $e->getMessage(),
            ];
        }
    }
 
    /* =====================
     * ESTIMASI — DESTROY
     * ===================== */
 
    /**
     * DELETE /inspection/report/{inspectionId}/estimasi/{estimasiId}
     */
    public function destroyEstimasi(int $inspectionId, int $estimasiId): array
    {
        $url = "{$this->baseUrl}/inspection/report/{$inspectionId}/estimasi/{$estimasiId}";
 
        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(15)
                ->delete($url);
 
            if ($response->failed()) {
                Log::error('Inspection API Error (destroyEstimasi)', [
                    'status'        => $response->status(),
                    'body'          => $response->body(),
                    'inspection_id' => $inspectionId,
                    'estimasi_id'   => $estimasiId,
                ]);
 
                return [
                    'success' => false,
                    'message' => 'Failed to delete estimasi',
                    'error'   => $response->json(),
                ];
            }
 
            return [
                'success' => true,
                'message' => 'Estimasi deleted successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Exception in InspectionReportApiService::destroyEstimasi', [
                'message'       => $e->getMessage(),
                'inspection_id' => $inspectionId,
                'estimasi_id'   => $estimasiId,
            ]);
 
            return [
                'success' => false,
                'message' => 'Service exception: ' . $e->getMessage(),
            ];
        }
    }

    public function downloadPDF(int $inspectionId)
    {
        $url = "{$this->baseUrl}/inspection/report/{$inspectionId}/document/download";

        // Kembalikan raw response, bukan array
        return Http::withToken($this->token)
            ->timeout(30)
            ->get($url);
    }

    public function previewPDF(int $inspectionId)
    {
        $url = "{$this->baseUrl}/inspection/report/{$inspectionId}/document/preview";

        // Ada typo di kode lama — kurang "/" sebelum document
        return Http::withToken($this->token)
            ->timeout(30)
            ->get($url);
    }

}
