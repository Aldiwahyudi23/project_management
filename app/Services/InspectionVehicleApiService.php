<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InspectionVehicleApiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.inspection_api.url'), '/');
        $this->token   = config('services.inspection_api.token');
    }

    /**
     * Get available brands
     * GET /api/inspection/vehicle-selection/brands
     */
    public function getBrands(): array
    {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/brands";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url);

        if ($response->failed()) {
            Log::error('Inspection API error - getBrands', [
                'url'    => $url,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch brands from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Get available models by brand
     * GET /api/inspection/vehicle-selection/models?brand_id={brandId}
     */
    public function getModels(int $brandId): array
    {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/models";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, [
                'brand_id' => $brandId
            ]);

        if ($response->failed()) {
            Log::error('Inspection API error - getModels', [
                'url'      => $url,
                'brand_id' => $brandId,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch models from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Get available types by brand and model
     * GET /api/inspection/vehicle-selection/types?brand_id={brandId}&model_id={modelId}
     */
    public function getTypes(int $brandId, int $modelId): array
    {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/types";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, [
                'brand_id' => $brandId,
                'model_id' => $modelId
            ]);

        if ($response->failed()) {
            Log::error('Inspection API error - getTypes', [
                'url'      => $url,
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch types from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Get available years by brand, model, and type
     * GET /api/inspection/vehicle-selection/years?brand_id={brandId}&model_id={modelId}&type_id={typeId}
     */
    public function getYears(int $brandId, int $modelId, int $typeId): array
    {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/years";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, [
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'type_id'  => $typeId
            ]);

        if ($response->failed()) {
            Log::error('Inspection API error - getYears', [
                'url'      => $url,
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'type_id'  => $typeId,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch years from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Get available CC by brand, model, type, and year
     * GET /api/inspection/vehicle-selection/cc?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}
     */
    public function getCc(int $brandId, int $modelId, int $typeId, int $year): array
    {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/cc";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, [
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'type_id'  => $typeId,
                'year'     => $year
            ]);

        if ($response->failed()) {
            Log::error('Inspection API error - getCc', [
                'url'      => $url,
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'type_id'  => $typeId,
                'year'     => $year,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch CC options from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Get available transmissions by brand, model, type, year, and cc
     * GET /api/inspection/vehicle-selection/transmissions?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}&cc={cc}
     */
    public function getTransmissions(int $brandId, int $modelId, int $typeId, int $year, int $cc): array
    {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/transmissions";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, [
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'type_id'  => $typeId,
                'year'     => $year,
                'cc'       => $cc
            ]);

        if ($response->failed()) {
            Log::error('Inspection API error - getTransmissions', [
                'url'      => $url,
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'type_id'  => $typeId,
                'year'     => $year,
                'cc'       => $cc,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch transmissions from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Get available fuel types by brand, model, type, year, cc, and transmission
     * GET /api/inspection/vehicle-selection/fuel-types?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}&cc={cc}&transmission_id={transmissionId}
     */
    public function getFuelTypes(int $brandId, int $modelId, int $typeId, int $year, int $cc, int $transmissionId): array
    {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/fuel-types";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, [
                'brand_id'        => $brandId,
                'model_id'        => $modelId,
                'type_id'         => $typeId,
                'year'            => $year,
                'cc'              => $cc,
                'transmission_id' => $transmissionId
            ]);

        if ($response->failed()) {
            Log::error('Inspection API error - getFuelTypes', [
                'url'             => $url,
                'brand_id'        => $brandId,
                'model_id'        => $modelId,
                'type_id'         => $typeId,
                'year'            => $year,
                'cc'              => $cc,
                'transmission_id' => $transmissionId,
                'status'          => $response->status(),
                'body'            => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch fuel types from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Get available market periods by complete selection
     * GET /api/inspection/vehicle-selection/market-periods?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}&cc={cc}&transmission_id={transmissionId}&fuel_type={fuelType}
     */
    public function getMarketPeriods(int $brandId, int $modelId, int $typeId, int $year, int $cc, int $transmissionId, string $fuelType): array
    {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/market-periods";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, [
                'brand_id'        => $brandId,
                'model_id'        => $modelId,
                'type_id'         => $typeId,
                'year'            => $year,
                'cc'              => $cc,
                'transmission_id' => $transmissionId,
                'fuel_type'       => $fuelType
            ]);

        if ($response->failed()) {
            Log::error('Inspection API error - getMarketPeriods', [
                'url'             => $url,
                'brand_id'        => $brandId,
                'model_id'        => $modelId,
                'type_id'         => $typeId,
                'year'            => $year,
                'cc'              => $cc,
                'transmission_id' => $transmissionId,
                'fuel_type'       => $fuelType,
                'status'          => $response->status(),
                'body'            => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch market periods from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Get final vehicle detail ID from complete selection
     * GET /api/inspection/vehicle-selection/get-detail?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}&cc={cc}&transmission_id={transmissionId}&fuel_type={fuelType}&market_period={marketPeriod}
     */
    public function getVehicleDetail(
        int $brandId, 
        int $modelId, 
        int $typeId, 
        int $year, 
        int $cc, 
        int $transmissionId, 
        string $fuelType, 
        ?string $marketPeriod = null
    ): array {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/get-detail";

        $params = [
            'brand_id'        => $brandId,
            'model_id'        => $modelId,
            'type_id'         => $typeId,
            'year'            => $year,
            'cc'              => $cc,
            'transmission_id' => $transmissionId,
            'fuel_type'       => $fuelType
        ];

        if ($marketPeriod) {
            $params['market_period'] = $marketPeriod;
        }

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, $params);

        if ($response->failed()) {
            Log::error('Inspection API error - getVehicleDetail', [
                'url'             => $url,
                'params'          => $params,
                'status'          => $response->status(),
                'body'            => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch vehicle detail from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Search vehicles with fuzzy search
     * GET /api/inspection/vehicle-selection/search?q={query}&limit={limit}
     */
    public function searchVehicles(string $query, int $limit = 20): array
    {
        $url = "{$this->baseUrl}/inspection/vehicle-selection/search";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, [
                'q'     => $query,
                'limit' => $limit
            ]);

        if ($response->failed()) {
            Log::error('Inspection API error - searchVehicles', [
                'url'    => $url,
                'query'  => $query,
                'limit'  => $limit,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to search vehicles from inspection service',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

    /**
     * Get vehicle by license plate (external API)
     * GET /api/inspection/check-vehicle?license_plate=XXX
     */
    public function getVehicleByPlate(string $plate): array
    {
        $url = "{$this->baseUrl}/inspection/check-vehicle";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->post($url, [
                'license_plate' => $plate
            ]);

        if ($response->failed()) {
            return $response->json(); // biar tetap sama format error dari backend A
        }

        // 🔥 RETURN ASLI TANPA DIUBAH
        return $response->json();
    }

}