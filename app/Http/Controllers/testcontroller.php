<?php

namespace App\Http\Controllers;

// use App\Models\DirectDB\Vehicle;
use App\Services\InspectionApiService;
use Illuminate\Http\Request;

class testcontroller extends Controller
{
    public function __construct(
        protected InspectionApiService $inspectionApi
    ) {
        //
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $directDBVehicles = Vehicle::find(1);

        // return response()->json([
        //     'success' => true,
        //     'vehicles' => $directDBVehicles,
        // ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $result = $this->inspectionApi->getInspectionDetail($id);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'debug'   => $result['error'] ?? null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'inspection' => $result['data'],
            ]);
        
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
