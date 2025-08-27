<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ShoppingBag, Calendar, Clock, MapPin, CheckCircle, XCircle, AlertCircle, X, Gift } from 'lucide-vue-next';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { jsonFetch } from '@/lib/csrf';

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

interface OrderPayment { id:number; status:string; method:string; amount:number }
interface Order {
  id: number;
  total_amount: number;
  status: string;
  order_date: string;
  created_at: string;
  order_items: OrderItem[];
  payment?: OrderPayment|null;
  confirmed_total?: number;
  is_fully_confirmed?: boolean;
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

// Local reactive copies for live updates
const liveOrders = ref<Order[]|null>(null);
const liveStats = ref<Partial<Stats>|null>(null);
let pollTimer: number | null = null;

async function pollOrders(){
  try {
    const res = await fetch('/api/dashboard/orders', { headers: { 'Accept':'application/json' } });
    if(!res.ok) return;
    const json = await res.json();
    liveOrders.value = json.orders;
    if(json.stats) liveStats.value = { ...liveStats.value, ...json.stats } as any;
  } catch {/* ignore */}
}

onMounted(()=> {
  pollOrders();
  pollTimer = window.setInterval(pollOrders, 5000); // 5s refresh
});
onBeforeUnmount(()=> { if(pollTimer) clearInterval(pollTimer); });

const cancellingReservations = ref<Set<number>>(new Set());
// Bill split modal state
const showSplitModal = ref(false);
const loadingSplit = ref(false);
const activeOrderId = ref<number|null>(null);
const orderDetail = ref<any>(null);
interface ParticipantItem { id:number; quantity:number }
const participants = ref<{ name:string; items:ParticipantItem[] }[]>([]);
const tipPercent = ref<number|null>(null);
const tipAmount = ref<number|null>(null);
const paymentMethod = ref<'cash'|'card'|'wallet'>('cash');
const savingSplit = ref(false);
const errorMessage = ref<string|null>(null);

const baseShares = computed(()=>{
  if(!orderDetail.value) return [] as number[];
  const shares = participants.value.map(()=>0);
  (orderDetail.value.items||[]).forEach((it:any)=>{
    const pricePerUnit = it.price;
    participants.value.forEach((p,pi)=>{
      const entry = p.items.find(ii=> ii.id===it.id);
      if(entry && entry.quantity>0){
        shares[pi] += pricePerUnit * entry.quantity;
      }
    });
  });
  return shares.map(s=> Number(s.toFixed(2)));
});
const allocationComplete = computed(()=>{
  if(!orderDetail.value) return false;
  return (orderDetail.value.items||[]).every((it:any)=>{
    const allocated = participants.value.reduce((s,p)=> s + (p.items.find(ii=> ii.id===it.id)?.quantity||0),0);
    return allocated === it.quantity;
  });
});
const remainingByItem = computed(()=>{
  const map: Record<number, number> = {};
  if(orderDetail.value){
    (orderDetail.value.items||[]).forEach((it:any)=>{
      const allocated = participants.value.reduce((s,p)=> s + (p.items.find(ii=> ii.id===it.id)?.quantity||0),0);
      map[it.id] = it.quantity - allocated;
    });
  }
  return map;
});
const totalBeforeTip = computed(()=> baseShares.value.reduce((a,b)=> a+b,0));
const computedTipAmount = computed(()=> tipAmount.value!=null ? Number(tipAmount.value) : (tipPercent.value!=null ? Number((totalBeforeTip.value * (tipPercent.value/100)).toFixed(2)) : 0));
const shareTotals = computed(()=> baseShares.value.map(bs=> totalBeforeTip.value ? Number((bs + computedTipAmount.value*(bs/totalBeforeTip.value)).toFixed(2)) : 0));

async function openSplit(order: Order){
  activeOrderId.value = order.id;
  showSplitModal.value = true;
  loadingSplit.value = true;
  errorMessage.value = null;
  console.log('[Split] Opening modal for order', order.id);
  try {
  const data = await jsonFetch(`/api/orders/${order.id}/bill-split`);
    orderDetail.value = data.order;
    if (data.billSplit) {
      tipAmount.value = Number(data.billSplit.tip_amount);
      participants.value = data.billSplit.participants.map((p:any)=> ({ name:p.name, items:(p.items||[]).map((it:any)=> typeof it==='object'? { id:it.id, quantity: it.quantity||1 }: { id:it, quantity:1 }) }));
    } else {
      participants.value = [{ name:'You', items: order.order_items.map(i=> ({ id:i.id, quantity:i.quantity })) }];
    }
    console.log('[Split] Loaded order detail & existing split', orderDetail.value);
  } catch(e){
    console.error('[Split] Failed to load split', e);
    errorMessage.value = (e as any).message || 'Failed to load split';
  } finally { loadingSplit.value=false; }
}
function addParticipant(){ participants.value.push({ name:'Guest '+ (participants.value.length+1), items:[]}); }
function removeParticipant(i:number){ if(participants.value.length>1) participants.value.splice(i,1); }
function addItemQuantity(pIndex:number, item:any){
  const remaining = remainingByItem.value[item.id] ?? 0;
  if(remaining<=0) return; // no more units to allocate
  const arr = participants.value[pIndex].items;
  const existing = arr.find(i=> i.id===item.id);
  if(existing){ existing.quantity = Math.min(existing.quantity + 1, existing.quantity + remaining); }
  else arr.push({ id:item.id, quantity:1 });
}
function removeItemQuantity(pIndex:number, item:any){
  const arr = participants.value[pIndex].items;
  const existing = arr.find(i=> i.id===item.id);
  if(!existing) return;
  existing.quantity -=1;
  if(existing.quantity<=0){ const idx=arr.indexOf(existing); arr.splice(idx,1); }
}
function setItemQuantity(pIndex:number, item:any, value:number){
  value = Math.max(0, Math.min(value, item.quantity));
  // compute how many units others already have
  const othersAllocated = participants.value.reduce((s,p,idx)=> idx===pIndex? s : s + (p.items.find(ii=> ii.id===item.id)?.quantity||0),0);
  const maxForThis = item.quantity - othersAllocated;
  if(value > maxForThis) value = maxForThis;
  const arr = participants.value[pIndex].items;
  let existing = arr.find(i=> i.id===item.id);
  if(!existing && value>0){ existing = { id:item.id, quantity:0 }; arr.push(existing); }
  if(existing){ existing.quantity = value; if(existing.quantity===0){ arr.splice(arr.indexOf(existing),1);} }
}
function closeSplit(){ showSplitModal.value=false; }
async function saveSplitAndPay(){
  if(!activeOrderId.value) return;
  if(!allocationComplete.value){
    alert('Please allocate all item quantities before saving.');
    return;
  }
  savingSplit.value = true;
  try {
    const splitPayload = { participants: participants.value.map(p=> ({ name:p.name, items:p.items.map(i=> ({ id:i.id, quantity:i.quantity })) })), tip_percent: tipPercent.value ?? undefined, tip_amount: tipAmount.value ?? undefined };
    await jsonFetch(`/orders/${activeOrderId.value}/bill-split`, { method:'POST', body: JSON.stringify(splitPayload) });
    await jsonFetch(`/orders/${activeOrderId.value}/payments`, { method:'POST', body: JSON.stringify({ method: paymentMethod.value, tip_amount: computedTipAmount.value }) });
    alert('Split & payment saved');
    showSplitModal.value=false;
  } catch(e){
    alert((e as any).message || 'Error');
  } finally { savingSplit.value=false; }
}

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
    const result = await jsonFetch(`/reservation/${reservationId}/cancel`, { method:'PATCH' });
    if (result) {
      alert('Reservation cancelled successfully!');
      // Reload the page to update the data
      router.visit('/dashboard');
    } else {
      throw new Error('Failed to cancel reservation');
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

// Payment status helpers (distinct styling from kitchen status)
const getPaymentStatusMeta = (payment?: OrderPayment|null) => {
  if(!payment) return { label: 'unpaid', color: 'text-gray-600 bg-gray-100 border border-gray-200' };
  switch(payment.status){
    case 'confirmed': return { label: 'payment confirmed', color: 'text-emerald-700 bg-emerald-50 border border-emerald-200' };
    case 'paid': return { label: 'awaiting confirm', color: 'text-amber-700 bg-amber-50 border border-amber-200' };
    case 'failed': return { label: 'payment failed', color: 'text-red-700 bg-red-50 border border-red-200' };
    default: return { label: payment.status, color: 'text-slate-700 bg-slate-50 border border-slate-200' };
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
                            <p class="text-xl font-bold text-primary">{{ liveStats?.total_orders ?? stats.total_orders }}</p>
                        </div>
                        <ShoppingBag class="h-6 w-6 text-primary/40" />
                    </div>
                </div>

                <div class="bg-[#fcfcf2] border border-primary/20 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-primary/60">Pending Orders</p>
                            <p class="text-xl font-bold text-primary">{{ liveStats?.pending_orders ?? stats.pending_orders }}</p>
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
                            <p class="text-xl font-bold text-green-600">{{ liveStats?.loyalty_points ?? stats.loyalty_points }}</p>
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
      <div v-if="(liveOrders || orders).length === 0" class="p-4 text-center text-primary/60 text-sm">
                            No orders yet
                        </div>

      <div v-else class="divide-y divide-primary/5">
              <div
    v-for="order in (liveOrders || orders)"
                :key="order.id"
                class="p-3 hover:bg-primary/5 transition-colors"
                @click="!order.payment && openSplit(order)"
              >
                                <div class="flex items-start justify-between mb-1">
                                    <div>
                                        <p class="font-medium text-primary text-sm">Order #{{ order.id }}</p>
                                        <p class="text-xs text-primary/60">{{ formatDateTime(order.created_at) }}</p>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                      <!-- Kitchen status badge -->
                                      <div class="flex items-center gap-1">
                                        <component :is="getStatusIcon(order.status)" class="h-3 w-3" :class="getStatusColor(order.status)" />
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium capitalize" :class="getStatusColor(order.status)">{{ order.status }}</span>
                                      </div>
                                      <!-- Payment status badge (independent styling) -->
                                      <div v-if="true" class="flex items-center gap-1">
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium capitalize" :class="getPaymentStatusMeta(order.payment).color">{{ getPaymentStatusMeta(order.payment).label }}</span>
                                      </div>
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

                                <div class="flex items-center justify-between gap-2 mt-1">
                                  <p class="font-semibold text-primary text-sm">৳{{ order.total_amount }}</p>
                                  <p class="text-[10px] text-primary/50" v-if="order.payment">৳{{ order.payment.amount }}</p>
                                </div>
                                <div class="mt-1 text-[10px]" :class="order.payment ? 'text-primary/60':'text-primary/50 italic'">
                                  <template v-if="order.payment">
                                    Method: {{ order.payment.method }}
                                    <span v-if="order.is_fully_confirmed" class="text-green-700 font-semibold ml-1">Fully Paid</span>
                                  </template>
                                  <template v-else>Tap to split & pay</template>
                                </div>
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
        <!-- Split Modal -->
        <div v-if="showSplitModal" class="fixed inset-0 bg-black/40 flex items-start md:items-center justify-center p-4 z-[10000] overflow-y-auto">
          <div class="bg-[#fcfcf2] rounded-lg shadow-xl w-full max-w-3xl p-6 relative">
            <button class="absolute top-2 right-2 text-primary/60 hover:text-primary" @click="closeSplit">✕</button>
            <h2 class="text-xl font-semibold text-primary mb-4">Order #{{ activeOrderId }} Split & Pay</h2>
            <div v-if="loadingSplit" class="text-sm text-primary/60">Loading...</div>
            <div v-else-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</div>
            <div v-else-if="!orderDetail" class="text-sm text-red-600">Failed to load order.</div>
            <div v-else class="space-y-6">
              <div>
                <h3 class="font-medium text-primary mb-2">Items</h3>
                <div class="flex flex-wrap gap-2 text-xs">
                  <div v-for="it in orderDetail.items" :key="it.id" class="px-2 py-1 border rounded border-primary/30 bg-primary/5">{{ it.name || it.dish?.bn }} x {{ it.quantity }} = ৳{{ (it.price ?? 0) * it.quantity }}</div>
                </div>
              </div>
              <div>
                <div class="flex items-center justify-between mb-2">
                  <h3 class="font-medium text-primary">Participants</h3>
                  <button @click="addParticipant" class="text-xs px-2 py-1 border border-primary rounded hover:bg-primary/10">Add</button>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                  <div v-for="(p,pi) in participants" :key="pi" class="border rounded p-3 space-y-2 bg-primary/5">
                    <div class="flex items-center gap-2">
                      <input v-model="p.name" class="border p-1 text-xs flex-1" />
                      <button v-if="participants.length>1" @click.stop="removeParticipant(pi)" class="text-red-500 text-xs">Remove</button>
                    </div>
                    <div class="space-y-1 text-[11px]">
                      <div v-for="it in orderDetail.items" :key="it.id" class="flex items-center justify-between gap-2">
                        <span class="flex-1 truncate cursor-pointer select-none" @click.stop="addItemQuantity(pi,it)">
                          {{ it.name || it.dish?.bn }} ({{ p.items.find(i=>i.id===it.id)?.quantity || 0 }}/{{ it.quantity }})
                          <span v-if="remainingByItem[it.id]>0" class="ml-1 text-[10px] text-orange-600">{{ remainingByItem[it.id] }} left</span>
                          <span v-else class="ml-1 text-[10px] text-green-600">ok</span>
                        </span>
                        <div class="flex items-center gap-1">
                          <button type="button" @click.stop="removeItemQuantity(pi,it)" class="px-2 py-0.5 rounded bg-primary/10 text-primary hover:bg-primary/20">-</button>
                          <input type="number" :max="it.quantity" min="0" :value="p.items.find(i=>i.id===it.id)?.quantity || 0" @change="setItemQuantity(pi,it, Number(($event.target as HTMLInputElement).value))" class="w-12 border rounded text-center bg-white/70" />
                          <button type="button" @click.stop="addItemQuantity(pi,it)" class="px-2 py-0.5 rounded bg-primary/10 text-primary hover:bg-primary/20">+</button>
                        </div>
                      </div>
                    </div>
                    <div class="text-[11px] text-primary/70">Share: ৳{{ baseShares[pi] }} | With Tip: ৳{{ shareTotals[pi] }}</div>
                  </div>
                </div>
              </div>
              <div class="grid md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-medium text-primary mb-1">Tip %</label>
                  <input type="number" v-model.number="tipPercent" class="border p-2 w-full text-sm" placeholder="e.g. 10" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-primary mb-1">Tip Amount</label>
                  <input type="number" v-model.number="tipAmount" class="border p-2 w-full text-sm" placeholder="e.g. 120" />
                </div>
                <div class="text-xs space-y-1">
                  <p>Subtotal: <strong>৳{{ totalBeforeTip.toFixed(2) }}</strong></p>
                  <p>Tip: <strong>৳{{ computedTipAmount.toFixed(2) }}</strong></p>
                  <p>Total: <strong>৳{{ (totalBeforeTip + computedTipAmount).toFixed(2) }}</strong></p>
                </div>
              </div>
              <div class="flex flex-wrap gap-4 items-center">
                <div class="text-sm">
                  <label class="block text-xs font-medium text-primary mb-1">Payment Method</label>
                  <select v-model="paymentMethod" class="border p-2 text-sm">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="wallet">Wallet</option>
                  </select>
                </div>
                <div class="ml-auto flex gap-2">
                  <button @click="closeSplit" type="button" class="px-4 py-2 border rounded text-sm">Cancel</button>
                  <div class="flex items-center gap-2">
                    <span v-if="!allocationComplete" class="text-[10px] text-red-600">Allocate all quantities</span>
                    <span v-else class="text-[10px] text-green-600">All quantities allocated</span>
                    <button @click="saveSplitAndPay" :disabled="savingSplit || !allocationComplete" class="px-4 py-2 bg-primary text-[#f5f5dc] rounded text-sm disabled:opacity-50">{{ savingSplit? 'Saving...' : 'Save & Pay' }}</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
    </AppLayout>
</template>
