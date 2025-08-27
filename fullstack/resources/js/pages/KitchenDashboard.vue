<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { jsonFetch } from '@/lib/csrf';
import { ShoppingBag, Timer, CheckCircle, ChevronDown, ChevronRight } from 'lucide-vue-next';
import { ref, onMounted, onBeforeUnmount } from 'vue';

interface Dish { id:number; bn:string; }
interface OrderItem { id:number; quantity:number; dish: Dish; }
interface Order { id:number; status:string; created_at:string; order_items: OrderItem[]; user:{ id:number; name:string }; }

defineProps<{
  orders: Order[];
  stats: { pending:number; preparing:number; ready:number };
  user: { name:string; role:string };
}>();

const data = ref<{ pending:any[]; in_progress:any[]; completed:any[]; counts:any }|null>(null);
const loading = ref(false);
let timer: number | null = null;
const expanded = ref<Set<number>>(new Set());
const toggleExpand = (id:number)=> {
  if(expanded.value.has(id)) expanded.value.delete(id); else expanded.value.add(id);
};

const fetchData = async () => {
  loading.value = true;
  try {
    data.value = await jsonFetch('/api/kitchen/orders');
  } catch (e:any){
    console.error('Kitchen fetch failed', e);
  } finally { loading.value = false; }
};
// Track per-order updating state to disable buttons & avoid duplicate requests
const updating: any = ref<Record<number, boolean>>({});

// Helper to move an order object between groups locally (optimistic UI)
function applyLocalStatus(orderId:number, newStatus:string){
  if(!data.value) return;
  const groups = ['pending','in_progress','completed'] as const;
  let order:any = null;
  for(const g of groups){
    const idx = (data.value as any)[g].findIndex((o:any)=>o.id===orderId);
    if(idx!==-1){
      order = (data.value as any)[g][idx];
      (data.value as any)[g].splice(idx,1);
      break;
    }
  }
  if(!order) return;
  order.status = newStatus;
  if(newStatus==='pending') (data.value as any).pending.unshift(order);
  else if(['preparing','ready'].includes(newStatus)) (data.value as any).in_progress.unshift(order);
  else if(newStatus==='delivered') (data.value as any).completed.unshift(order);
  // cancelled => drop
}

const updateStatus = async (orderId:number, status:string) => {
  if(updating.value[orderId]) return; // guard
  updating.value[orderId] = true;

  // Keep original status for rollback
  let originalStatus:string|undefined;
  if(data.value){
    const all = [...data.value.pending, ...data.value.in_progress, ...data.value.completed];
    originalStatus = all.find(o=>o.id===orderId)?.status;
  }

  // Optimistic update
  applyLocalStatus(orderId, status);

  try {
    try {
      const json = await jsonFetch(`/api/kitchen/orders/${orderId}/status`, { method:'PATCH', body: JSON.stringify({ status }) });
      const serverStatus = json?.order?.status || status;
      if(serverStatus!==status) applyLocalStatus(orderId, serverStatus);
    } catch(e:any){
      if(originalStatus) applyLocalStatus(orderId, originalStatus);
      alert(e?.message || 'Failed to update status');
    }
  } catch (e:any){
    if(originalStatus) applyLocalStatus(orderId, originalStatus);
    alert(e?.message || 'Network error updating status');
  } finally {
    updating.value[orderId] = false;
  }
};

function itemName(it: any){
  return it?.dish?.name_bn || it?.dish?.name_en || it?.dish?.name || it?.name || it?.dish_name || it?.title || `Item ${it.id}`;
}

onMounted(() => {
  fetchData();
  timer = window.setInterval(fetchData, 5000);
});
onBeforeUnmount(()=> { if (timer) clearInterval(timer); });
</script>

<template>
  <Head title="Kitchen Dashboard" />
  <AppLayout :breadcrumbs="[{title:'Kitchen', href:'/dashboard/kitchen'}]">
    <div class="p-4 space-y-4">
      <h1 class="text-2xl font-semibold text-primary">Kitchen Dashboard</h1>
      <div class="grid gap-3 md:grid-cols-3">
        <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded"><p class="text-xs text-primary/60">Pending</p><p class="text-xl font-bold text-primary flex items-center gap-1"><Timer class="w-4 h-4" /> {{ stats.pending }}</p></div>
        <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded"><p class="text-xs text-primary/60">Preparing</p><p class="text-xl font-bold text-primary flex items-center gap-1"><ShoppingBag class="w-4 h-4" /> {{ stats.preparing }}</p></div>
        <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded"><p class="text-xs text-primary/60">Ready</p><p class="text-xl font-bold text-primary flex items-center gap-1"><CheckCircle class="w-4 h-4" /> {{ stats.ready }}</p></div>
      </div>

      <div class="bg-[#fcfcf2] border border-primary/20 rounded-lg overflow-hidden">
        <div class="p-4 border-b border-primary/10 flex justify-between items-center">
          <h2 class="font-semibold text-primary">Active Orders</h2>
        </div>
        <div class="divide-y divide-primary/10">
          <div v-if="orders.length===0" class="p-4 text-sm text-primary/60">No active orders.</div>
          <div v-for="order in orders" :key="order.id" class="p-3">
            <div class="flex justify-between text-sm font-medium text-primary">
              <span>#{{ order.id }} • {{ order.user.name }}</span>
              <span class="capitalize">{{ order.status }}</span>
            </div>
            <ul class="mt-1 text-xs text-primary/70 space-y-0.5">
              <li v-for="item in order.order_items" :key="item.id">{{ itemName(item) }} x {{ item.quantity }}</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="grid md:grid-cols-3 gap-4 mt-6" v-if="data">
        <div class="bg-[#fcfcf2] border border-primary/20 rounded p-3">
          <h3 class="font-semibold text-primary mb-2 text-sm">Pending</h3>
          <div v-for="o in data.pending" :key="o.id" class="mb-2 bg-white/60 rounded border border-primary/10 overflow-hidden">
            <button class="w-full flex items-center justify-between px-2 py-1 text-xs font-medium text-primary hover:bg-primary/10" @click="toggleExpand(o.id)">
              <span class="flex items-center gap-1"><component :is="expanded.has(o.id)?ChevronDown:ChevronRight" class="w-3 h-3" /> #{{ o.id }} <span class="text-primary/60">• {{ o.items.length }} items</span></span>
              <span class="uppercase tracking-wide text-[10px] bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded">pending</span>
            </button>
            <transition name="fade">
              <div v-if="expanded.has(o.id)" class="px-3 pb-3">
                <ul class="mt-2 text-[11px] text-primary/70 space-y-0.5">
                  <li v-for="i in o.items" :key="i.id">{{ itemName(i) }} <span class="font-medium">x {{ i.quantity }}</span></li>
                </ul>
                <div class="flex gap-2 mt-3">
                  <button class="px-2 py-0.5 text-[11px] rounded bg-primary text-[#f5f5dc] hover:bg-primary/90 transition disabled:opacity-50" :disabled="updating[o.id]" @click="updateStatus(o.id,'preparing')">{{ updating[o.id] ? '...' : 'Start' }}</button>
                  <button class="px-2 py-0.5 text-[11px] rounded bg-red-600 text-white hover:bg-red-500 transition disabled:opacity-50" :disabled="updating[o.id]" @click="updateStatus(o.id,'cancelled')">Cancel</button>
                </div>
              </div>
            </transition>
          </div>
        </div>
        <div class="bg-[#fcfcf2] border border-primary/20 rounded p-3">
          <h3 class="font-semibold text-primary mb-2 text-sm">In Progress</h3>
          <div v-for="o in data.in_progress" :key="o.id" class="mb-2 bg-white/60 rounded border border-primary/10 overflow-hidden">
            <button class="w-full flex items-center justify-between px-2 py-1 text-xs font-medium text-primary hover:bg-primary/10" @click="toggleExpand(o.id)">
              <span class="flex items-center gap-1"><component :is="expanded.has(o.id)?ChevronDown:ChevronRight" class="w-3 h-3" /> #{{ o.id }}</span>
              <span class="uppercase tracking-wide text-[10px]" :class="o.status==='ready' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">{{ o.status }}</span>
            </button>
            <transition name="fade">
              <div v-if="expanded.has(o.id)" class="px-3 pb-3">
                <ul class="mt-2 text-[11px] text-primary/70 space-y-0.5">
                  <li v-for="i in o.items" :key="i.id">{{ itemName(i) }} <span class="font-medium">x {{ i.quantity }}</span></li>
                </ul>
                <div class="flex gap-2 mt-3">
                  <button v-if="o.status==='preparing'" class="px-2 py-0.5 text-[11px] rounded bg-primary text-[#f5f5dc] hover:bg-primary/90 transition disabled:opacity-50" :disabled="updating[o.id]" @click="updateStatus(o.id,'ready')">{{ updating[o.id] ? '...' : 'Mark Ready' }}</button>
                  <button v-if="o.status==='ready'" class="px-2 py-0.5 text-[11px] rounded bg-primary text-[#f5f5dc] hover:bg-primary/90 transition disabled:opacity-50" :disabled="updating[o.id]" @click="updateStatus(o.id,'delivered')">{{ updating[o.id] ? '...' : 'Deliver' }}</button>
                  <button v-if="o.status!=='ready'" class="px-2 py-0.5 text-[11px] rounded bg-red-600 text-white hover:bg-red-500 transition disabled:opacity-50" :disabled="updating[o.id]" @click="updateStatus(o.id,'cancelled')">Cancel</button>
                </div>
              </div>
            </transition>
          </div>
        </div>
        <div class="bg-[#fcfcf2] border border-primary/20 rounded p-3">
          <h3 class="font-semibold text-primary mb-2 text-sm">Completed</h3>
          <div v-for="o in data.completed" :key="o.id" class="mb-2 bg-white/60 rounded border border-primary/10 overflow-hidden">
            <button class="w-full flex items-center justify-between px-2 py-1 text-xs font-medium text-primary hover:bg-primary/10" @click="toggleExpand(o.id)">
              <span class="flex items-center gap-1"><component :is="expanded.has(o.id)?ChevronDown:ChevronRight" class="w-3 h-3" /> #{{ o.id }}</span>
              <span class="uppercase tracking-wide text-[10px] bg-primary/10 text-primary px-2 py-0.5 rounded">done</span>
            </button>
            <transition name="fade">
              <div v-if="expanded.has(o.id)" class="px-3 pb-3">
                <ul class="mt-2 text-[11px] text-primary/70 space-y-0.5">
                  <li v-for="i in o.items" :key="i.id">{{ itemName(i) }} <span class="font-medium">x {{ i.quantity }}</span></li>
                </ul>
              </div>
            </transition>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.fade-enter-active,.fade-leave-active { transition: all .18s ease; }
.fade-enter-from,.fade-leave-to { opacity:0; transform: translateY(-2px); }
</style>
