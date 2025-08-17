<template>
  <AppLayout title="Loyalty Points">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Loyalty Points & Rewards
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Loyalty Points Summary Card -->
        <div class="bg-[#fcfcf2] dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold mb-2">Your Loyalty Points</h3>
                <div class="text-3xl font-bold text-green-600">
                  {{ loyaltyPoints }}
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  Points earned from completed orders
                </p>
              </div>
              <div class="text-right">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  Earn 1 point per $1 spent
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                  Redeem points for discounts
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Order History -->
        <div class="bg-[#fcfcf2] dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <h3 class="text-lg font-semibold mb-4">Order History</h3>
            
            <div v-if="loading" class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 dark:border-gray-100 mx-auto"></div>
              <p class="mt-2 text-gray-600 dark:text-gray-400">Loading orders...</p>
            </div>

            <div v-else-if="orders.length === 0" class="text-center py-8">
              <p class="text-gray-600 dark:text-gray-400">No orders found.</p>
            </div>

            <div v-else class="space-y-4">
              <div
                v-for="order in orders"
                :key="order.id"
                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
              >
                <div class="flex justify-between items-start mb-3">
                  <div>
                    <h4 class="font-medium">Order #{{ order.id }}</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                      {{ formatDate(order.order_date) }}
                    </p>
                  </div>
                  <div class="text-right">
                    <div class="font-semibold text-lg">${{ order.total_amount }}</div>
                    <div class="flex items-center gap-2">
                      <span
                        :class="getStatusClass(order.status)"
                        class="px-2 py-1 rounded-full text-xs font-medium"
                      >
                        {{ order.status }}
                      </span>
                      <span
                        v-if="order.loyalty_points_earned > 0"
                        class="text-green-600 text-sm font-medium"
                      >
                        +{{ order.loyalty_points_earned }} pts
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Order Items -->
                <div class="space-y-2 mb-3">
                  <div
                    v-for="item in order.items"
                    :key="`${order.id}-${item.dish_name}`"
                    class="flex justify-between text-sm"
                  >
                    <span>{{ item.dish_name }} x{{ item.quantity }}</span>
                    <span>${{ (item.price * item.quantity).toFixed(2) }}</span>
                  </div>
                </div>

                <!-- Complete Order Button -->
                <div v-if="order.status !== 'delivered' && order.status !== 'cancelled'" class="mt-3">
                  <button
                    @click="completeOrder(order.id)"
                    :disabled="completingOrder === order.id"
                    class="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors"
                  >
                    <span v-if="completingOrder === order.id">Completing...</span>
                    <span v-else>Mark as Completed</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

interface OrderItem {
  dish_name: string
  quantity: number
  price: number
}

interface Order {
  id: number
  total_amount: number
  status: string
  order_date: string
  loyalty_points_earned: number
  items: OrderItem[]
}

const loyaltyPoints = ref(0)
const orders = ref<Order[]>([])
const loading = ref(true)
const completingOrder = ref<number | null>(null)

const fetchLoyaltyPoints = async () => {
  try {
    const response = await fetch('/api/loyalty-points')
    const data = await response.json()
    loyaltyPoints.value = data.loyalty_points
  } catch (error) {
    console.error('Error fetching loyalty points:', error)
  }
}

const fetchOrderHistory = async () => {
  try {
    loading.value = true
    const response = await fetch('/api/order-history')
    const data = await response.json()
    orders.value = data.orders
    loyaltyPoints.value = data.total_loyalty_points
  } catch (error) {
    console.error('Error fetching order history:', error)
  } finally {
    loading.value = false
  }
}

const completeOrder = async (orderId: number) => {
  try {
    completingOrder.value = orderId
    const response = await fetch(`/orders/${orderId}/complete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })

    if (response.ok) {
      const data = await response.json()
      loyaltyPoints.value = data.total_loyalty_points
      
      // Update the order status in the local state
      const orderIndex = orders.value.findIndex(order => order.id === orderId)
      if (orderIndex !== -1) {
        orders.value[orderIndex].status = 'delivered'
        orders.value[orderIndex].loyalty_points_earned = data.loyalty_points_earned
      }
      
      // Show success message
      alert(`Order completed! You earned ${data.loyalty_points_earned} loyalty points.`)
    } else {
      const errorData = await response.json()
      alert(errorData.message || 'Failed to complete order')
    }
  } catch (error) {
    console.error('Error completing order:', error)
    alert('Failed to complete order. Please try again.')
  } finally {
    completingOrder.value = null
  }
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getStatusClass = (status: string) => {
  switch (status) {
    case 'delivered':
      return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    case 'cancelled':
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    case 'pending':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    default:
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
  }
}

onMounted(() => {
  fetchOrderHistory()
})
</script>
