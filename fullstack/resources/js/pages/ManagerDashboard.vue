<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Users, ShoppingBag, Calendar, CheckCircle } from 'lucide-vue-next';

interface Metrics { users:number; orders:number; revenue:number; reservations:number; active_reservations:number; delivered_orders:number }
interface Order { id:number; total_amount:number; status:string; created_at:string; user:{ name:string } }
interface Reservation { id:number; reservation_date:string; reservation_time:string; status:string; user:{ name:string } }

defineProps<{ metrics:Metrics; recentOrders:Order[]; recentReservations:Reservation[]; user:{ name:string; role:string }; dailyReport:{ date:string; sales:number; reservation_count:number; table_turnover_rate:number; top_items:{ dish_id:number; name:string; quantity:number; revenue:number }[] } }>();

// Format currency in Bangladeshi Taka (৳)
const formatCurrency = (n:number) => new Intl.NumberFormat('en-BD',{style:'currency',currency:'BDT'}).format(n);
</script>

<template>
  <Head title="Manager Dashboard" />
  <AppLayout :breadcrumbs="[{title:'Manager', href:'/dashboard/manager'}]">
    <div class="p-4 space-y-4">
      <h1 class="text-2xl font-semibold text-primary">Manager Dashboard</h1>
      <div class="grid gap-3 md:grid-cols-6">
        <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded col-span-2"><p class="text-xs text-primary/60">Total Users</p><p class="text-xl font-bold text-primary flex items-center gap-1"><Users class="w-4 h-4" /> {{ metrics.users }}</p></div>
        <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded"><p class="text-xs text-primary/60">Orders</p><p class="text-xl font-bold text-primary flex items-center gap-1"><ShoppingBag class="w-4 h-4" /> {{ metrics.orders }}</p></div>
        <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded"><p class="text-xs text-primary/60">Delivered</p><p class="text-xl font-bold text-primary flex items-center gap-1"><CheckCircle class="w-4 h-4" /> {{ metrics.delivered_orders }}</p></div>
        <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded"><p class="text-xs text-primary/60">Reservations</p><p class="text-xl font-bold text-primary flex items-center gap-1"><Calendar class="w-4 h-4" /> {{ metrics.reservations }}</p></div>
        <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded"><p class="text-xs text-primary/60">Revenue</p><p class="text-xl font-bold text-green-700 flex items-center gap-1">৳ {{ formatCurrency(metrics.revenue).replace(/[^0-9.,]/g,'') }}</p></div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div class="bg-[#fcfcf2] border border-primary/20 rounded">
          <div class="p-3 border-b border-primary/10 font-semibold text-primary">Recent Orders</div>
          <div class="divide-y divide-primary/10">
            <div v-if="recentOrders.length===0" class="p-4 text-sm text-primary/60">No orders.</div>
            <div v-for="o in recentOrders" :key="o.id" class="p-3 text-xs flex justify-between">
              <span>#{{ o.id }} • {{ o.user.name }}</span>
              <span>{{ o.status }} • {{ o.total_amount }}</span>
            </div>
          </div>
        </div>
        <div class="bg-[#fcfcf2] border border-primary/20 rounded">
          <div class="p-3 border-b border-primary/10 font-semibold text-primary">Recent Reservations</div>
          <div class="divide-y divide-primary/10">
            <div v-if="recentReservations.length===0" class="p-4 text-sm text-primary/60">No reservations.</div>
            <div v-for="r in recentReservations" :key="r.id" class="p-3 text-xs flex justify-between">
              <span>#{{ r.id }} • {{ r.user.name }}</span>
              <span class="capitalize">{{ r.status }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-1">
        <div class="bg-[#fcfcf2] border border-primary/20 rounded">
          <div class="p-3 border-b border-primary/10 font-semibold text-primary">Top Selling Items</div>
          <div v-if="!dailyReport.top_items.length" class="p-4 text-xs text-primary/60">No sales yet today.</div>
          <table v-else class="w-full text-xs">
            <thead>
              <tr class="text-left text-primary/60">
                <th class="p-2">Item</th>
                <th class="p-2 text-right">Qty</th>
                <th class="p-2 text-right">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="it in dailyReport.top_items" :key="it.dish_id" class="border-t border-primary/10">
                <td class="p-2">{{ it.name }}</td>
                <td class="p-2 text-right">{{ it.quantity }}</td>
                <td class="p-2 text-right">{{ formatCurrency(it.revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
