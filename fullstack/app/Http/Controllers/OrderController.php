<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * List orders for kitchen staff grouped by phase.
     */
    public function kitchenOrders()
    {
        $this->authorizeKitchen();

        $orders = Order::with(['orderItems.dish', 'user'])
            ->orderBy('created_at', 'asc')
            ->get();

        $group = fn($statuses) => $orders->whereIn('status', (array) $statuses)->values()->map(fn($o) => [
            'id' => $o->id,
            'status' => $o->status,
            'created_at' => $o->created_at,
            'user' => ['id' => $o->user?->id, 'name' => $o->user?->name],
            'items' => $o->orderItems->map(fn($i) => [
                'id' => $i->id,
                'quantity' => $i->quantity,
                'dish' => [
                    'id' => $i->dish?->id,
                    'bn' => $i->dish?->bn,
                ]
            ])
        ]);

        return response()->json([
            'pending' => $group('pending'),
            'in_progress' => $group(['preparing', 'ready']),
            'completed' => $group('delivered'),
            'counts' => [
                'pending' => $orders->where('status', 'pending')->count(),
                'preparing' => $orders->where('status', 'preparing')->count(),
                'ready' => $orders->where('status', 'ready')->count(),
                'delivered' => $orders->where('status', 'delivered')->count(),
            ]
        ]);
    }

    /**
     * Update status for an order by kitchen staff.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeKitchen();

        $request->validate([
            'status' => 'required|string|in:pending,preparing,ready,delivered,cancelled'
        ]);

        $new = $request->string('status')->toString();
        $current = $order->status;
        $allowedTransitions = [
            'pending' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready' => ['delivered', 'cancelled'],
            'delivered' => [],
            'cancelled' => [],
        ];

        if (!isset($allowedTransitions[$current]) || !in_array($new, $allowedTransitions[$current], true)) {
            return response()->json([
                'message' => "Invalid transition from $current to $new"
            ], 422);
        }

        $order->update(['status' => $new]);

        if ($new === 'delivered') {
            // Award points if not already awarded (markAsCompleted handles idempotency)
            $order->markAsCompleted();
        }

        return response()->json([
            'message' => 'Status updated',
            'order' => $order->fresh()->load('orderItems.dish', 'user')
        ]);
    }

    /**
     * Ensure current user is kitchen or manager.
     */
    protected function authorizeKitchen(): void
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['kitchen', 'manager'])) {
            abort(403, 'Unauthorized');
        }
    }
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
            // Log detailed error for debugging
            Log::error('Order placement failed', [
                'user_id' => Auth::id(),
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to place order. Please try again.',
                'error' => $e->getMessage(),
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
