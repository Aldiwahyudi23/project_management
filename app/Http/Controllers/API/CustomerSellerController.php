<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Customer\Customer;
use App\Models\MasterData\Customer\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerSellerController extends Controller
{
    /**
     * 🔍 GET CUSTOMER BY PHONE
     * Example: /api/customer-seller/find-by-phone?phone=08123
     */
    public function findByPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string'
        ]);

        $customer = Customer::where('phone', $request->phone)->first();

        if (!$customer) {
            return response()->json([
                'success' => true,
                'found' => false,
                'message' => 'Customer not found',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'found' => true,
            'message' => 'Customer found',
            'data' => $customer
        ]);
    }

    /**
     * Get customer by ID
     */
    public function show($id)
    {
        $customer = Customer::find($id);
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    /**
     * STORE (Create Customer + Seller)
     * NOTE: Seller always new, Customer might be reused
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'phone' => 'required|string|max:20',

            'email'   => 'nullable|email',
            'address' => 'nullable|string',

            // SELLER fields (wajib diisi untuk seller)
            'inspection_id'       => 'required|exists:inspections,id',
            'inspection_area'     => 'required|string|max:255',
            'inspection_address'  => 'required|string',
            'link_maps'           => 'nullable|string',
            'unit_holder_name'    => 'nullable|string|max:255',
            'unit_holder_phone'   => 'nullable|string|max:20',
            'settings'            => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // 🔥 CEK CUSTOMER BY PHONE DULU
            $customer = Customer::where('phone', $request->phone)->first();

            if (!$customer) {
                // Create new customer if not exists
                $customer = Customer::create([
                    'name'    => $request->name,
                    'phone'   => $request->phone,
                    'email'   => $request->email,
                    'address' => $request->address,
                ]);
            } else {
                // OPTIONAL: Update customer data jika ada perubahan
                // Bisa di-uncomment jika ingin update data customer
                /*
                $customer->update([
                    'name'    => $request->name,
                    'email'   => $request->email,
                    'address' => $request->address,
                ]);
                */
            }

            // 🔥 SELLER SELALU BARU (untuk inspeksi baru)
            $seller = Seller::create([
                'customer_id'         => $customer->id,
                'inspection_id'       => $request->inspection_id,
                'inspection_area'     => $request->inspection_area,
                'inspection_address'  => $request->inspection_address,
                'link_maps'           => $request->link_maps,
                'unit_holder_name'    => $request->unit_holder_name,
                'unit_holder_phone'   => $request->unit_holder_phone,
                'settings'            => $request->settings,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Seller created successfully',
                'data' => [
                    'customer' => $customer,
                    'seller'   => $seller,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to store data',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * UPDATE CUSTOMER ONLY (tanpa affect seller)
     * Untuk update data customer saja
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'sometimes|string|max:255',
            'phone'   => 'sometimes|string|max:20',
            'email'   => 'nullable|email',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $customer = Customer::findOrFail($id);

            $customer->update($request->only(['name', 'phone', 'email', 'address']));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}