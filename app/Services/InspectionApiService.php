<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InspectionApiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.inspection_api.url'), '/');
        $this->token   = config('services.inspection_api.token');
    }

    //Untuk mendapatkan data Detail Inspection dari API insepction
    public function getInspectionDetail(int $id): array
    {
        $url = "{$this->baseUrl}/inspection/details/{$id}";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url);

        if ($response->failed()) {
            // Log::error('inspection API error', [
            //     'url'    => $url,
            //     'status' => $response->status(),
            //     'body'   => $response->body(),
            // ]);

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
    // Untuk mendapatkan ID dari pencarian lewat API 
    public function searchInspections(string $search): array
    {
        $url = "{$this->baseUrl}/inspection/search";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, [
                'search' => $search
            ]);

        if ($response->failed()) {
            // Log::error('inspection search API error', [
            //     'url'    => $url,
            //     'status' => $response->status(),
            //     'body'   => $response->body(),
            // ]);

            return [];
        }

        $data = $response->json('data')
            ?? $response->json('inspection')
            ?? $response->json();

        // Ambil hanya ID inspection
        return collect($data)
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();
    }

    // =============================Form Inspection Untuk menangani semua yang di Form Inspection=============================
    
    //Untuk mendapatkan Form Inspeksi dari data inspeksi 
    public function getFormInspection(int $id): array
    {
        $url = "{$this->baseUrl}/inspection/{$id}/template-structure";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url);

        if ($response->failed()) {
            // Log::error('form inspection API error', [
            //     'url'    => $url,
            //     'status' => $response->status(),
            //     'body'   => $response->body(),
            // ]);

            return [
                'success' => false,
                'message' => 'form inspection API error',
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
     * Upload Single / Multiple Images to Inspection API
     */
    public function uploadImage(array $payload, UploadedFile|array|null $files): array
    {
        $url = "{$this->baseUrl}/inspection/inspection-images/upload";

        // Kalau tidak ada file
        if (!$files) {
            return [
                'success' => false,
                'message' => 'No files provided'
            ];
        }

        // Normalisasi jadi array
        if (!is_array($files)) {
            $files = [$files];
        }

        try {

            $request = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(30);

            // Attach semua file
            foreach ($files as $file) {

                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $request->attach(
                    'images[]', // penting pakai []
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                );
            }

            $response = $request->post($url, $payload);

            if ($response->failed()) {

                // Log::error('Inspection Upload API Error', [
                //     'url'      => $url,
                //     'status'   => $response->status(),
                //     'response' => $response->body(),
                //     'payload'  => $payload,
                // ]);

                return [
                    'success' => false,
                    'message' => 'Upload image failed',
                    'error'   => $response->json(),
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json('data'),
            ];

        } catch (\Throwable $e) {

            // Log::error('Inspection Upload Exception', [
            //     'url'   => $url,
            //     'error' => $e->getMessage(),
            // ]);

            return [
                'success' => false,
                'message' => 'Server error during upload',
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete Image from Inspection API
     */
    public function deleteImage(int $id): array
    {
        $url = "{$this->baseUrl}/inspection/inspection-images/{$id}";

        try {

            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(20)
                ->delete($url);

            if ($response->failed()) {

                // Log::error('Inspection Delete API Error', [
                //     'url'      => $url,
                //     'status'   => $response->status(),
                //     'response' => $response->body(),
                // ]);

                return [
                    'success' => false,
                    'message' => 'Delete image failed',
                ];
            }

            return [
                'success' => true,
            ];

        } catch (\Throwable $e) {

            Log::error('Inspection Delete Exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Server error during delete',
                'error'   => $e->getMessage(),
            ];
        }
    }

      /**
     * Delete inspection result + all images in one item
     */
    public function deleteInspectionItem(int $inspectionId, int $ItemId): array
    {
        $url = "{$this->baseUrl}/inspection/{$inspectionId}/items/{$ItemId}";

        try {

            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(20)
                ->delete($url);

            if ($response->failed()) {

                Log::error('Inspection Item Delete API Error', [
                    'url'      => $url,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Delete inspection item failed',
                ];
            }

            return [
                'success' => true,
            ];

        } catch (\Throwable $e) {

            Log::error('Inspection Item Delete Exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Server error during delete',
                'error'   => $e->getMessage(),
            ];
        }
    }

    public function getUnassignedImages(int $inspectionId): array
    {
        $url = "{$this->baseUrl}/inspection/inspection-images/unassigned/{$inspectionId}";

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

    //Untuk mengganti item_id gambar di poto bebas jadi sudah di assign ke item tertentu
    public function patchAssignImages(
        array $imageIds,
        int $inspectionItemId
    ): array {

        $url = "{$this->baseUrl}/inspection/inspection-images/assign";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->patch($url, [
                'inspection_item_id' => $inspectionItemId,
                'image_ids' => $imageIds
            ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => 'inspection API error',
                'error' => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data' => $response->json()
        ];
    }
    
public function putInspectionVehicle(
    int $inspectionId,
    string $licensePlate,
    int $vehicleId,
    string $vehicleName
): array {

    $url = "{$this->baseUrl}/inspection/{$inspectionId}/vehicle";

    $response = Http::withToken($this->token)
        ->acceptJson()
        ->asJson()
        ->timeout(10)
        ->put($url, [
            'license_plate' => $licensePlate,
            'vehicle_name'  => $vehicleName,
            'vehicle_id'    => $vehicleId
        ]);

    if ($response->failed()) {
        return [
            'success' => false,
            'message' => 'Inspection API error',
            'status'  => $response->status(),
            'error'   => $response->json()
        ];
    }

    return [
        'success' => true,
        'data' => $response->json()
    ];
}

    /**
     * Submit hasil inspeksi ke Backend A
     * Dipanggil dari SaveFormInspectionController setelah validasi lokal selesai.
     *
     * Payload yang dikirim:
     * {
     *   "inspection_id": 11,
     *   "submitted_by":  5,
     *   "submitted_at":  "2026-02-11 07:12:00",
     *   "results": [
     *     {
     *       "inspection_item_id": 1,
     *       "status":     null,
     *       "note":       "STNK SIAP",
     *       "extra_data": null,
     *       "image_ids":  []
     *     },
     *     ...
     *   ]
     * } inspection-images/unassigned
     */
    public function submitInspectionForm(array $payload): array
    {
        $url = "{$this->baseUrl}/inspection/save-form";

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(30)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('Submit Inspection Results API Error', [
                    'url'      => $url,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                    'payload'  => array_merge($payload, ['results' => '[...truncated...]']),
                ]);

                return [
                    'success' => false,
                    'message' => $response->json('message') ?? 'Gagal menyimpan hasil inspeksi ke server',
                    'error'   => $response->json(),
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json('data') ?? $response->json(),
            ];

        } catch (\Throwable $e) {
            Log::error('Submit Inspection Results Exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Server error saat mengirim hasil inspeksi',
                'error'   => $e->getMessage(),
            ];
        }
    }
}
