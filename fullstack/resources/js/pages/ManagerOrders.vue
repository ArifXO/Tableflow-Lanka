<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { jsonFetch } from '@/lib/csrf';
import { CheckCircle, RefreshCw, Search, ShieldCheck } from 'lucide-vue-next';

interface Payment { id:number; amount:number; tip_amount:number; method:string; status:string; created_at:string }
interface Item { id:number; dish_name:string; quantity:number; price:number }
interface OrderRow { id:number; user:{ id:number; name:string }; status:string; order_date:string; total_amount:number; paid_total:number; confirmed_total:number; is_fully_confirmed:boolean; payments:Payment[]; items:Item[] }

const loading = ref(true);
const orders = ref<OrderRow[]>([]);
const errorMsg = ref('');
const filter = ref('');
const statusFilter = ref('');
const refreshing = ref(false);

async function load() {
	try {
		refreshing.value = true; if(loading.value===false) { /* keep spinner subtle */ }
		const url = new URL('/api/manager/orders', window.location.origin);
		if(statusFilter.value) url.searchParams.set('status', statusFilter.value);
		const data = await jsonFetch(url.toString());
		orders.value = data.orders || [];
	} catch(e:any) {
		errorMsg.value = e.message;
	} finally { loading.value = false; refreshing.value = false; }
}

onMounted(load);

async function confirmPayment(paymentId:number){
	try {
		await jsonFetch(`/api/manager/payments/${paymentId}/confirm`, { method:'POST' });
		// Update payment status locally
		for(const o of orders.value){
			const p = o.payments.find(p=>p.id===paymentId);
			if(p){ p.status='confirmed'; o.confirmed_total = o.payments.filter(x=>x.status==='confirmed').reduce((s,x)=>s+x.amount,0); o.is_fully_confirmed = o.confirmed_total >= o.total_amount; break; }
		}
	} catch(e:any){
		alert(e.message);
	}
}

const filtered = computed(()=> orders.value.filter(o => !filter.value || o.user.name.toLowerCase().includes(filter.value.toLowerCase()) || String(o.id).includes(filter.value)));
const format = (n:number) => new Intl.NumberFormat('en-US',{style:'currency', currency:'USD'}).format(n);

const breadcrumbs = computed(()=> [{ title:'Manager', href:'/dashboard/manager' }, { title:'Payments', href:'/dashboard/manager/orders' }]);
</script>

<template>
	<Head title="Manager Orders" />
	<AppLayout :breadcrumbs="breadcrumbs">
		<div class="p-6 space-y-4">
			<div class="flex flex-wrap gap-4 items-center justify-between">
				<h1 class="text-2xl font-semibold text-primary flex items-center gap-2"><ShieldCheck class="w-6 h-6" /> Orders & Payments</h1>
				<div class="flex gap-2 items-center">
					<div class="relative">
						<Search class="w-4 h-4 absolute left-2 top-2.5 text-primary/50" />
						<input v-model="filter" placeholder="Search orders" class="pl-7 pr-3 py-1.5 text-sm border rounded bg-white/70 focus:outline-none focus:ring-1 focus:ring-primary/40" />
					</div>
					<select v-model="statusFilter" @change="load" class="text-sm border rounded px-2 py-1 bg-white/70">
						<option value="">All Statuses</option>
						<option value="pending">Pending</option>
						<option value="preparing">Preparing</option>
						<option value="delivered">Delivered</option>
						<option value="cancelled">Cancelled</option>
					</select>
					<button @click="load" class="flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-primary text-white disabled:opacity-50" :disabled="refreshing">
						<RefreshCw :class="{'animate-spin': refreshing}" class="w-4 h-4" /> Refresh
					</button>
				</div>
			</div>
			<div v-if="loading" class="text-primary/60 text-sm">Loading orders...</div>
			<div v-else-if="errorMsg" class="text-red-600 text-sm">{{ errorMsg }}</div>
			<div v-else class="space-y-4">
				<div v-if="!filtered.length" class="text-sm text-primary/60">No matching orders.</div>
				<div v-for="o in filtered" :key="o.id" class="border rounded bg-white/70 shadow-sm">
					<div class="p-3 flex flex-wrap gap-4 justify-between text-xs md:text-sm">
						<div><span class="font-semibold text-primary">#{{ o.id }}</span> • {{ o.user.name }}</div>
						<div>Status: <span class="capitalize font-medium" :class="{'text-green-600': o.status==='delivered', 'text-yellow-600': o.status==='pending'}">{{ o.status }}</span></div>
						<div>Date: {{ o.order_date }}</div>
						<div>Total: <span class="font-medium">{{ format(o.total_amount) }}</span></div>
						<div>Paid: <span class="font-medium" :class="{'text-green-700': o.paid_total>=o.total_amount}">{{ format(o.paid_total) }}</span></div>
						<div>Confirmed: <span class="font-medium" :class="{'text-green-700': o.is_fully_confirmed}">{{ format(o.confirmed_total) }}</span></div>
					</div>
					<div class="px-3 pb-3">
						<details class="bg-primary/5 rounded">
							<summary class="cursor-pointer select-none px-3 py-2 text-xs font-medium text-primary">Items ({{ o.items.length }})</summary>
							<div class="p-3">
								<table class="w-full text-xs">
									<thead>
										<tr class="text-left text-primary/60"><th class="py-1">Item</th><th class="py-1">Qty</th><th class="py-1 text-right">Price</th></tr>
									</thead>
									<tbody>
										<tr v-for="it in o.items" :key="it.id" class="border-t border-primary/10">
											<td class="py-1">{{ it.dish_name }}</td>
											<td class="py-1">{{ it.quantity }}</td>
											<td class="py-1 text-right">{{ format(it.price) }}</td>
										</tr>
									</tbody>
								</table>
							</div>
						</details>
						<details class="bg-primary/5 rounded mt-2">
							<summary class="cursor-pointer select-none px-3 py-2 text-xs font-medium text-primary flex items-center gap-2">Payments ({{ o.payments.length }})</summary>
							<div class="p-3 space-y-2">
								<div v-if="!o.payments.length" class="text-xs text-primary/60">No payments yet.</div>
								<div v-for="p in o.payments" :key="p.id" class="flex flex-wrap items-center gap-2 text-xs border rounded px-2 py-1 bg-white/80">
									<div>#{{ p.id }}</div>
									<div>{{ format(p.amount) }}</div>
									<div class="capitalize">{{ p.method }}</div>
									<div class="capitalize" :class="{'text-green-700': p.status==='confirmed', 'text-yellow-700': p.status==='paid', 'text-red-600': p.status==='failed'}">{{ p.status }}</div>
									<div class="text-primary/60">{{ p.created_at }}</div>
									<button v-if="p.status!=='confirmed'" @click="confirmPayment(p.id)" class="ml-auto flex items-center gap-1 px-2 py-0.5 rounded text-[11px] bg-green-600 text-white hover:bg-green-700">
										<CheckCircle class="w-3 h-3" /> Confirm
									</button>
									<div v-else class="ml-auto flex items-center gap-1 text-green-700 font-medium text-[11px]"><CheckCircle class="w-3 h-3" /> Confirmed</div>
								</div>
							</div>
						</details>
					</div>
				</div>
			</div>
		</div>
	</AppLayout>
</template>

<style scoped>
/* Tailwind @apply with dynamic slash opacity caused build issue; emulate with rgba */
details[open] summary { background-color: rgba(59,16,16,0.1); }
</style>
