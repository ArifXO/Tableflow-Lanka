<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ShoppingBag, Calendar, Clock, MapPin, CheckCircle, XCircle, AlertCircle, X, Gift } from 'lucide-vue-next';
import { ref } from 'vue';

interface Dish {
  id: number;
  cat: string;
  img: string;
  bn: string;
  en: string;
  price: number;
}

interface OrderItem {
  id: number;
  quantity: number;
  price: number;
  dish: Dish;
}

interface Order {
  id: number;
  total_amount: number;
  status: string;
  order_date: string;
  created_at: string;
  order_items: OrderItem[];
}

interface Table {
  id: number;
  number: number;
  seats: number;
}

interface Reservation {
  id: number;
  reservation_date: string;
  reservation_time: string;
  party_size: number;
  status: string;
  created_at: string;
  table: Table;
}

interface Stats {
  total_orders: number;
  total_reservations: number;
  pending_orders: number;
  confirmed_reservations: number;
  loyalty_points: number;
  completed_orders: number;
}

defineProps<{
  orders: Order[];
  reservations: Reservation[];
  stats: Stats;
}>();

const cancellingReservations = ref<Set<number>>(new Set());

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const isReservationCancellable = (reservation: Reservation) => {
  if (reservation.status !== 'confirmed') {
    return false;
  }

  // Handle different date formats
  let reservationDateTime;
  try {
    // Extract just the date part from Laravel datetime format
    let dateStr = reservation.reservation_date;

    // If it's a full datetime string like "2025-08-05T00:00:00.000000Z", extract just the date
    if (dateStr.includes('T')) {
      dateStr = dateStr.split('T')[0];
    }

    let timeStr = reservation.reservation_time;

    // Ensure time has seconds if missing
    if (timeStr && timeStr.split(':').length === 2) {
      timeStr = timeStr + ':00';
    }

    // Create ISO format string
    const isoString = `${dateStr}T${timeStr}`;
    reservationDateTime = new Date(isoString);

    // Check if parsing was successful
    if (isNaN(reservationDateTime.getTime())) {
      return false;
    }

  } catch {
    return false;
  }

  const now = new Date();
  return reservationDateTime > now;
};

const cancelReservation = async (reservationId: number) => {
  if (cancellingReservations.value.has(reservationId)) return;

  if (!confirm('Are you sure you want to cancel this reservation?')) return;

  cancellingReservations.value.add(reservationId);

  try {
    const response = await fetch(`/reservation/${reservationId}/cancel`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    const result = await response.json();

    if (response.ok) {
      alert('Reservation cancelled successfully!');
      // Reload the page to update the data
      router.visit('/dashboard');
    } else {
      throw new Error(result.message || 'Failed to cancel reservation');
    }
  } catch (error) {
    console.error('Cancel reservation error:', error);
    alert('Failed to cancel reservation. Please try again.');
  } finally {
    cancellingReservations.value.delete(reservationId);
  }
};

const getStatusColor = (status: string) => {
  switch (status) {
    case 'confirmed':
    case 'ready':
    case 'delivered':
      return 'text-green-600 bg-green-50';
    case 'pending':
    case 'preparing':
      return 'text-yellow-600 bg-yellow-50';
    case 'cancelled':
      return 'text-red-600 bg-red-50';
    default:
      return 'text-gray-600 bg-gray-50';
  }
};

const getStatusIcon = (status: string) => {
  switch (status) {
    case 'confirmed':
    case 'ready':
    case 'delivered':
      return CheckCircle;
    case 'cancelled':
      return XCircle;
    default:
      return AlertCircle;
  }
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const formatDateTime = (dateString: string) => {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <!-- Stats Cards -->
            <div class="grid auto-rows-min gap-3 md:grid-cols-5">
                <div class="bg-[#fcfcf2] border border-primary/20 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-primary/60">Total Orders</p>
                            <p class="text-xl font-bold text-primary">{{ stats.total_orders }}</p>
                        </div>
                        <ShoppingBag class="h-6 w-6 text-primary/40" />
                    </div>
                </div>

                <div class="bg-[#fcfcf2] border border-primary/20 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-primary/60">Pending Orders</p>
                            <p class="text-xl font-bold text-primary">{{ stats.pending_orders }}</p>
                        </div>
                        <Clock class="h-6 w-6 text-primary/40" />
                    </div>
                </div>

                <div class="bg-[#fcfcf2] border border-primary/20 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-primary/60">Total Reservations</p>
                            <p class="text-xl font-bold text-primary">{{ stats.total_reservations }}</p>
                        </div>
                        <Calendar class="h-6 w-6 text-primary/40" />
                    </div>
                </div>

                <div class="bg-[#fcfcf2] border border-primary/20 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-primary/60">Confirmed Reservations</p>
                            <p class="text-xl font-bold text-primary">{{ stats.confirmed_reservations }}</p>
                        </div>
                        <CheckCircle class="h-6 w-6 text-primary/40" />
                    </div>
                </div>

                <div class="bg-[#fcfcf2] border border-primary/20 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-primary/60">Loyalty Points</p>
                            <p class="text-xl font-bold text-green-600">{{ stats.loyalty_points }}</p>
                        </div>
                        <Gift class="h-6 w-6 text-green-500" />
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Recent Orders -->
                <div class="bg-[#fcfcf2] border border-primary/20 rounded-lg">
                    <div class="p-4 border-b border-primary/10">
                        <h2 class="text-lg font-semibold text-primary flex items-center gap-2">
                            <ShoppingBag class="h-4 w-4" />
                            Recent Orders
                        </h2>
                    </div>

                    <div class="max-h-64 overflow-y-auto">
                        <div v-if="orders.length === 0" class="p-4 text-center text-primary/60 text-sm">
                            No orders yet
                        </div>

                        <div v-else class="divide-y divide-primary/5">
                            <div
                                v-for="order in orders"
                                :key="order.id"
                                class="p-3 hover:bg-primary/5 transition-colors"
                            >
                                <div class="flex items-start justify-between mb-1">
                                    <div>
                                        <p class="font-medium text-primary text-sm">Order #{{ order.id }}</p>
                                        <p class="text-xs text-primary/60">{{ formatDateTime(order.created_at) }}</p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <component
                                            :is="getStatusIcon(order.status)"
                                            class="h-3 w-3"
                                            :class="getStatusColor(order.status)"
                                        />
                                        <span
                                            class="px-1.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                            :class="getStatusColor(order.status)"
                                        >
                                            {{ order.status }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-1">
                                    <p class="text-xs text-primary/80">
                                        {{ order.order_items.length }} item{{ order.order_items.length !== 1 ? 's' : '' }}
                                    </p>
                                    <div class="text-xs text-primary/60 mt-0.5">
                                        <span v-for="(item, index) in order.order_items.slice(0, 2)" :key="item.id">
                                            {{ item.dish.bn }} ({{ item.quantity }}x){{ index < Math.min(order.order_items.length, 2) - 1 ? ', ' : '' }}
                                        </span>
                                        <span v-if="order.order_items.length > 2">
                                            ... and {{ order.order_items.length - 2 }} more
                                        </span>
                                    </div>
                                </div>

                                <p class="font-semibold text-primary text-sm">৳{{ order.total_amount }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Reservations -->
                <div class="bg-[#fcfcf2] border border-primary/20 rounded-lg">
                    <div class="p-4 border-b border-primary/10">
                        <h2 class="text-lg font-semibold text-primary flex items-center gap-2">
                            <Calendar class="h-4 w-4" />
                            Recent Reservations
                        </h2>
                    </div>

                    <div class="max-h-64 overflow-y-auto">
                        <div v-if="reservations.length === 0" class="p-4 text-center text-primary/60 text-sm">
                            No reservations yet
                        </div>

                        <div v-else class="divide-y divide-primary/5">
                            <div
                                v-for="reservation in reservations"
                                :key="reservation.id"
                                class="p-3 hover:bg-primary/5 transition-colors"
                            >
                                <div class="flex items-start justify-between mb-1">
                                    <div>
                                        <p class="font-medium text-primary text-sm">Reservation #{{ reservation.id }}</p>
                                        <p class="text-xs text-primary/60">{{ formatDateTime(reservation.created_at) }}</p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <component
                                            :is="getStatusIcon(reservation.status)"
                                            class="h-3 w-3"
                                            :class="getStatusColor(reservation.status)"
                                        />
                                        <span
                                            class="px-1.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                            :class="getStatusColor(reservation.status)"
                                        >
                                            {{ reservation.status }}
                                        </span>
                                    </div>
                                </div>

                                <div class="space-y-0.5 text-xs text-primary/80">
                                    <div class="flex items-center gap-1">
                                        <Calendar class="h-3 w-3" />
                                        <span>{{ formatDate(reservation.reservation_date) }} at {{ reservation.reservation_time }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <MapPin class="h-3 w-3" />
                                        <span>Table {{ reservation.table.number }} ({{ reservation.table.seats }} seats)</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="w-3 h-3 flex items-center justify-center text-xs">👥</span>
                                        <span>{{ reservation.party_size }} guest{{ reservation.party_size !== 1 ? 's' : '' }}</span>
                                    </div>
                                </div>

                                <!-- Cancel Button -->
                                <div v-if="isReservationCancellable(reservation)" class="mt-2 flex justify-end">
                                    <button
                                        @click="cancelReservation(reservation.id)"
                                        :disabled="cancellingReservations.has(reservation.id)"
                                        class="flex items-center gap-1 px-2 py-1 text-xs text-red-600 bg-red-50 border border-red-200 rounded hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <X class="h-3 w-3" />
                                        {{ cancellingReservations.has(reservation.id) ? 'Cancelling...' : 'Cancel' }}
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
