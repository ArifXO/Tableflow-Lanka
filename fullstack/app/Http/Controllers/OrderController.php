<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    // Store a newly created order in storage.
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            // Calculate total amount
            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($request->items as $item) {
                $dish = Dish::findOrFail($item['id']);
                $quantity = $item['quantity'];
                $itemTotal = $dish->price * $quantity;
                $totalAmount += $itemTotal;

                $orderItemsData[] = [
                    'dish_id' => $dish->id,
                    'quantity' => $quantity,
                    'price' => $dish->price,
                ];
            }

            // Create the order
            $order = Order::create([
                'user_id' => Auth::id(),
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'notes' => $request->notes,
                'order_date' => now(),
            ]);

            // Create order items
            foreach ($orderItemsData as $itemData) {
                $order->orderItems()->create($itemData);
            }

            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully!',
                'order' => $order->load('orderItems.dish'),
                'potential_loyalty_points' => $order->calculateLoyaltyPoints()
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'message' => 'Failed to place order. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete an order and award loyalty points
     *
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeOrder(int $orderId)
    {
        try {
            $order = Order::where('user_id', Auth::id())->findOrFail($orderId);
            
            if ($order->isCompleted()) {
                return response()->json([
                    'message' => 'Order is already completed.',
                    'order' => $order
                ], 400);
            }

            if ($order->isCancelled()) {
                return response()->json([
                    'message' => 'Cannot complete a cancelled order.',
                    'order' => $order
                ], 400);
            }

            DB::beginTransaction();

            $pointsEarned = $order->calculateLoyaltyPoints();
            $order->markAsCompleted();
            
            // Refresh the user to get updated loyalty points
            $user = Auth::user()->fresh();

            DB::commit();

            return response()->json([
                'message' => 'Order completed successfully!',
                'order' => $order->load('orderItems.dish'),
                'loyalty_points_earned' => $pointsEarned,
                'total_loyalty_points' => $user->getLoyaltyPoints()
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'message' => 'Failed to complete order. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's loyalty points
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLoyaltyPoints()
    {
        $user = Auth::user();
        
        return response()->json([
            'loyalty_points' => $user->getLoyaltyPoints(),
            'user' => $user->only(['id', 'name', 'email'])
        ], 200);
    }

    /**
     * Get user's order history with loyalty points information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderHistory()
    {
        $orders = Auth::user()->orders()
            ->with('orderItems.dish')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'order_date' => $order->order_date,
                    'loyalty_points_earned' => $order->isCompleted() ? $order->calculateLoyaltyPoints() : 0,
                    'items' => $order->orderItems->map(function ($item) {
                        return [
                            'dish_name' => $item->dish->name,
                            'quantity' => $item->quantity,
                            'price' => $item->price
                        ];
                    })
                ];
            });

        return response()->json([
            'orders' => $orders,
            'total_loyalty_points' => Auth::user()->getLoyaltyPoints()
        ], 200);
    }
}
