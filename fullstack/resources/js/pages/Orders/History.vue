<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';

interface OrderItem { dish_name: string; quantity:number; price:number }
interface OrderRow { id:number; total_amount:number|string; status:string; order_date:string; loyalty_points_earned:number; items:OrderItem[] }

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
            </div>
            <table class="w-full text-xs">
              <thead>
                <tr class="text-left text-primary/70">
                  <th class="py-1 pr-2">Item</th>
                  <th class="py-1 pr-2">Qty</th>
                  <th class="py-1 pr-2">Price</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="it in o.items" :key="it.dish_name+it.quantity" class="border-t border-primary/10">
                  <td class="py-1 pr-2">{{ it.dish_name }}</td>
                  <td class="py-1 pr-2">{{ it.quantity }}</td>
                  <td class="py-1 pr-2">৳{{ it.price }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
