<?php

namespace App\Http\Controllers\Api\AppInspection;

use App\Http\Controllers\Controller;
use App\Services\InspectionVehicleApiService;
use Illuminate\Http\Request;

class InspectionVehicleController extends Controller
{
    protected InspectionVehicleApiService $inspectionVehicleApi;

    public function __construct(InspectionVehicleApiService $inspectionVehicleApi)
    {
        $this->inspectionVehicleApi = $inspectionVehicleApi;
    }

    /**
     * Get brands for dropdown
     */
    public function getBrands()
    {
        $result = $this->inspectionVehicleApi->getBrands();
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch brands'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    /**
     * Get models by brand
     */
    public function getModels(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer'
        ]);

        $result = $this->inspectionVehicleApi->getModels($request->brand_id);
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch models'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    /**
     * Get types by brand and model
     */
    public function getTypes(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer'
        ]);

        $result = $this->inspectionVehicleApi->getTypes(
            $request->brand_id,
            $request->model_id
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch types'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    /**
     * Get years by brand, model, and type
     */
    public function getYears(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer',
            'type_id'  => 'required|integer'
        ]);

        $result = $this->inspectionVehicleApi->getYears(
            $request->brand_id,
            $request->model_id,
            $request->type_id
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch years'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    /**
     * Get CC by brand, model, type, and year
     */
    public function getCc(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer',
            'type_id'  => 'required|integer',
            'year'     => 'required|integer'
        ]);

        $result = $this->inspectionVehicleApi->getCc(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch CC options'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    /**
     * Get transmissions by brand, model, type, year, and cc
     */
    public function getTransmissions(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer',
            'type_id'  => 'required|integer',
            'year'     => 'required|integer',
            'cc'       => 'required|integer'
        ]);

        $result = $this->inspectionVehicleApi->getTransmissions(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year,
            $request->cc
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transmissions'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    /**
     * Get fuel types by brand, model, type, year, cc, and transmission
     */
    public function getFuelTypes(Request $request)
    {
        $request->validate([
            'brand_id'        => 'required|integer',
            'model_id'        => 'required|integer',
            'type_id'         => 'required|integer',
            'year'            => 'required|integer',
            'cc'              => 'required|integer',
            'transmission_id' => 'required|integer'
        ]);

        $result = $this->inspectionVehicleApi->getFuelTypes(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year,
            $request->cc,
            $request->transmission_id
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch fuel types'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    /**
     * Get market periods by complete selection
     */
    public function getMarketPeriods(Request $request)
    {
        $request->validate([
            'brand_id'        => 'required|integer',
            'model_id'        => 'required|integer',
            'type_id'         => 'required|integer',
            'year'            => 'required|integer',
            'cc'              => 'required|integer',
            'transmission_id' => 'required|integer',
            'fuel_type'       => 'required|string'
        ]);

        $result = $this->inspectionVehicleApi->getMarketPeriods(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year,
            $request->cc,
            $request->transmission_id,
            $request->fuel_type
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch market periods'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    /**
     * Get final vehicle detail ID
     */
    public function getVehicleDetail(Request $request)
    {
        $request->validate([
            'brand_id'        => 'required|integer',
            'model_id'        => 'required|integer',
            'type_id'         => 'required|integer',
            'year'            => 'required|integer',
            'cc'              => 'required|integer',
            'transmission_id' => 'required|integer',
            'fuel_type'       => 'required|string',
            'market_period'   => 'nullable|string'
        ]);

        $result = $this->inspectionVehicleApi->getVehicleDetail(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year,
            $request->cc,
            $request->transmission_id,
            $request->fuel_type,
            $request->market_period
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicle detail'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    /**
     * Search vehicles
     */
    public function searchVehicles(Request $request)
    {
        $request->validate([
            'q'     => 'required|string|min:2',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $result = $this->inspectionVehicleApi->searchVehicles(
            $request->q,
            $request->limit ?? 20
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search vehicles'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }
}