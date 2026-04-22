<?php
// app/Http/Controllers/Api/Seller/InventoryController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
   

    public function lowStockProducts()
    {
        $products = Product::where('seller_id', auth()->id())
            ->whereColumn('quantity', '<=', 'min_stock_alert')
            ->where('is_active', true)
            ->get();
        
        return response()->json($products);
    }

    public function outOfStockProducts()
    {
        $products = Product::where('seller_id', auth()->id())
            ->where('quantity', 0)
            ->where('is_active', true)
            ->get();
        
        return response()->json($products);
    }

    public function inventoryLogs(Request $request)
    {
        $query = InventoryLog::whereHas('product', function($q) {
            $q->where('seller_id', auth()->id());
        })->with('product', 'user');
        
        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        
        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        // Date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }
        
        $logs = $query->latest()->paginate(5);
        
        return response()->json($logs);
    }

    public function bulkStockUpdate(Request $request)
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.product_id' => 'required|exists:products,id',
            'updates.*.quantity' => 'required|integer|min:0',
        ]);
        
        $sellerId = auth()->id();
        $results = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($validated['updates'] as $update) {
                $product = Product::where('seller_id', $sellerId)
                    ->findOrFail($update['product_id']);
                
                $oldQuantity = $product->quantity;
                $change = $update['quantity'] - $oldQuantity;
                
                if ($change != 0) {
                    $product->quantity = $update['quantity'];
                    $product->save();
                    
                    $product->logInventoryChange(
                        'adjustment',
                        $change,
                        $oldQuantity,
                        'Bulk stock update'
                    );
                }
                
                $results[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $product->quantity
                ];
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Bulk stock update completed',
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Bulk update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function inventorySummary()
    {
        $sellerId = auth()->id();
        
        $summary = [
            'total_products' => Product::where('seller_id', $sellerId)->count(),
            'total_stock_value' => Product::where('seller_id', $sellerId)
                ->select(DB::raw('SUM(quantity * price) as total_value'))
                ->value('total_value') ?? 0,
            'total_units' => Product::where('seller_id', $sellerId)->sum('quantity'),
            'low_stock_count' => Product::where('seller_id', $sellerId)
                ->whereColumn('quantity', '<=', 'min_stock_alert')
                ->count(),
            'out_of_stock_count' => Product::where('seller_id', $sellerId)
                ->where('quantity', 0)
                ->count(),
        ];
        
        return response()->json($summary);
    }
}