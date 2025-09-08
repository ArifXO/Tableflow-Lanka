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
                    'name_bn' => $i->dish?->name_bn,
                    'name_en' => $i->dish?->name_en,
                    'name' => $i->dish?->name_bn ?? $i->dish?->name_en,
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
            $order->markAsCompleted();
        }

        return response()->json([
            'message' => 'Status updated',
            'order' => $order->fresh()->load('orderItems.dish', 'user')
        ]);
    }


    protected function authorizeKitchen(): void
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['kitchen', 'manager'])) {
            abort(403, 'Unauthorized');
        }
    }


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


            $order = Order::create([
                'user_id' => Auth::id(),
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'notes' => $request->notes,
                'order_date' => now(),
            ]);


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


    public function getLoyaltyPoints()
    {
        $user = Auth::user();

        return response()->json([
            'loyalty_points' => $user->getLoyaltyPoints(),
            'user' => $user->only(['id', 'name', 'email'])
        ], 200);
    }


    public function getOrderHistory()
    {
        $orders = Auth::user()->orders()
            ->with(['orderItems.dish','payments'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                $confirmedTotal = (float)$order->payments->where('status','confirmed')->sum('amount');
                return [
                    'id' => $order->id,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'order_date' => $order->order_date,
                    'loyalty_points_earned' => $order->isCompleted() ? $order->calculateLoyaltyPoints() : 0,
                    'items' => $order->orderItems->map(function ($item) {
                        $dishName = $item->dish?->name_en ?? $item->dish?->name_bn ?? 'Item';
                        return [
                            'dish_name' => $dishName,
                            'quantity' => $item->quantity,
                            'price' => $item->price
                        ];
                    }),
                    'payments' => $order->payments->map(fn($p)=>[
                        'id'=>$p->id,
                        'amount'=>(float)$p->amount,
                        'tip_amount'=>(float)$p->tip_amount,
                        'method'=>$p->method,
                        'status'=>$p->status,
                        'created_at'=>$p->created_at?->toDateTimeString(),
                    ]),
                    'confirmed_total' => $confirmedTotal,
                ];
            });

        return response()->json([
            'orders' => $orders,
            'total_loyalty_points' => Auth::user()->getLoyaltyPoints()
        ], 200);
    }
}
