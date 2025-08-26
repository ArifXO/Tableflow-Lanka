<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';

interface OrderItem { dish_name: string; quantity:number; price:number }
interface Payment { id:number; amount:number; tip_amount:number; method:string; status:string; created_at:string }
interface OrderRow { id:number; total_amount:number|string; status:string; order_date:string; loyalty_points_earned:number; items:OrderItem[]; payments:Payment[]; confirmed_total:number }

const loading = ref(true);
const orders = ref<OrderRow[]>([]);
const totalPoints = ref(0);
const errorMsg = ref('');

onMounted(async () => {
  try {
    const res = await fetch('/api/order-history', { headers: { 'Accept':'application/json' }});
    const data = await res.json();
    if(!res.ok) throw new Error(data.message || 'Failed');
    orders.value = data.orders || [];
    totalPoints.value = data.total_loyalty_points || 0;
  } catch(e:any){
    errorMsg.value = e.message || 'Error loading order history';
  } finally { loading.value=false; }
});

const breadcrumbs = computed(()=> [{ title:'Order History', href:'/orders/history' }]);
</script>

<template>
  <Head title="Order History" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-primary">Order History</h1>
        <div class="text-sm text-primary/70">Total Loyalty Points: <span class="font-bold text-primary">{{ totalPoints }}</span></div>
      </div>
      <div v-if="loading" class="text-primary/60 text-sm">Loading...</div>
      <div v-else-if="errorMsg" class="text-red-600 text-sm">{{ errorMsg }}</div>
      <div v-else>
        <div v-if="orders.length === 0" class="text-primary/60 text-sm">No orders yet.</div>
        <div v-else class="space-y-4">
          <div v-for="o in orders" :key="o.id" class="border rounded-md p-4 bg-primary/5">
            <div class="flex flex-wrap gap-4 justify-between text-sm mb-2">
              <div><span class="font-medium text-primary">Order #{{ o.id }}</span></div>
              <div>Status: <span class="font-medium capitalize" :class="{'text-green-600': o.status==='delivered', 'text-yellow-600': o.status==='pending'}">{{ o.status }}</span></div>
              <div>Date: {{ o.order_date }}</div>
              <div>Total: ৳{{ o.total_amount }}</div>
              <div>Loyalty Points: <span class="font-medium text-primary">{{ o.loyalty_points_earned }}</span></div>
              <div v-if="o.payments?.length" class="text-green-700">Confirmed: ৳{{ o.confirmed_total.toFixed ? o.confirmed_total.toFixed(2):o.confirmed_total }}</div>
            </div>
            <details class="mt-1 bg-white/60 rounded">
              <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-primary/80 flex items-center justify-between">
                <span>Items ({{ o.items.length }})</span>
                <span class="text-primary/60 text-[10px]">Click to toggle</span>
              </summary>
              <div class="p-3">
                <table class="w-full text-xs">
                  <thead>
                    <tr class="text-left text-primary/70">
                      <th class="py-1 pr-2">Item</th>
                      <th class="py-1 pr-2">Qty</th>
                      <th class="py-1 pr-2">Price</th>
                      <th class="py-1 pr-2 text-right">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="it in o.items" :key="it.dish_name+it.quantity" class="border-t border-primary/10">
                      <td class="py-1 pr-2">{{ it.dish_name }}</td>
                      <td class="py-1 pr-2">{{ it.quantity }}</td>
                      <td class="py-1 pr-2">৳{{ it.price }}</td>
                      <td class="py-1 pr-2 text-right">৳{{ (Number(it.price) * it.quantity).toFixed(2) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </details>
            <details v-if="o.payments?.length" class="mt-3">
              <summary class="cursor-pointer text-xs font-medium text-primary/80">Payments ({{ o.payments.length }})</summary>
              <div class="mt-2 space-y-1">
                <div v-for="p in o.payments" :key="p.id" class="text-[11px] flex flex-wrap gap-3 items-center border rounded px-2 py-1 bg-white/70">
                  <span>#{{ p.id }}</span>
                  <span>৳{{ p.amount }}</span>
                  <span class="capitalize">{{ p.method }}</span>
                  <span :class="{'text-green-700': p.status==='confirmed','text-yellow-700':p.status==='paid'}">{{ p.status }}</span>
                  <span class="text-primary/50">{{ p.created_at }}</span>
                </div>
              </div>
            </details>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
