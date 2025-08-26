<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface OrderItem { id:number; name:string; quantity:number; price:number; total:number }
interface ParticipantDraft { name:string; items:number[] }

const props = defineProps<{
  order: { id:number; status:string; total_amount:string|number; items: OrderItem[] };
  billSplit: null | { id:number; tip_amount:string|number; total_before_tip:string|number; total_after_tip:string|number; participants: { id:number; name:string; share_before_tip:string|number; share_tip:string|number; share_total:string|number; items:number[] }[] };
}>();

const participants = ref<ParticipantDraft[]>(props.billSplit ? props.billSplit.participants.map(p => ({ name: p.name, items: [...p.items] })) : [ { name:'You', items: props.order.items.map(i=>i.id) } ]);
const tipPercent = ref<number | null>(null);
const tipAmount = ref<number | null>(props.billSplit ? Number(props.billSplit.tip_amount) : null);
const submitting = ref(false);

const itemTotals = computed(()=> props.order.items.reduce((m,i)=> { m[i.id]= i.total; return m;}, {} as Record<number, number>));

const baseShares = computed(()=> {
  const map: Record<number, number[]> = {};
  participants.value.forEach((p,idx)=> p.items.forEach(it=> { (map[it]=map[it]||[]).push(idx); }));
  const shares = participants.value.map(()=>0);
  Object.entries(map).forEach(([itemId, pIdxs])=>{
    const total = itemTotals.value[Number(itemId)] || 0;
    const portion = total / pIdxs.length;
    pIdxs.forEach(pi=> shares[pi]+= portion);
  });
  return shares.map(s=> Number(s.toFixed(2)));
});

const totalBeforeTip = computed(()=> baseShares.value.reduce((a,b)=> a+b,0));
const computedTipAmount = computed(()=> {
  if (tipAmount.value != null) return Number(tipAmount.value.toFixed(2));
  if (tipPercent.value != null) return Number((totalBeforeTip.value * (tipPercent.value/100)).toFixed(2));
  return 0;
});
const shareTotals = computed(()=> {
  return baseShares.value.map(bs=> {
    if (totalBeforeTip.value === 0) return 0;
    const tipShare = computedTipAmount.value * (bs/ totalBeforeTip.value);
    return Number((bs + tipShare).toFixed(2));
  });
});

function addParticipant(){ participants.value.push({ name: 'Guest '+ (participants.value.length+1), items: [] }); }
function removeParticipant(i:number){ if (participants.value.length>1) participants.value.splice(i,1); }
function toggleItem(pIndex:number, itemId:number){ const arr=participants.value[pIndex].items; const pos = arr.indexOf(itemId); if(pos>=0) arr.splice(pos,1); else arr.push(itemId); }

async function submit(){
  submitting.value = true;
  try {
    const payload = {
      participants: participants.value.map(p=> ({ name: p.name, items: p.items })),
      tip_percent: tipPercent.value ?? undefined,
      tip_amount: tipAmount.value ?? undefined,
    };
    const res = await fetch(`/orders/${props.order.id}/bill-split`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if(!res.ok) throw new Error(data.message || 'Failed');
    alert('Bill split saved.');
    router.visit(`/orders/${props.order.id}/bill-split`);
  } catch(e:any){
    alert(e.message || 'Error');
  } finally { submitting.value=false; }
}
</script>

<template>
  <Head title="Bill Split" />
  <AppLayout :breadcrumbs="[{title:'Bill Split', href:`/orders/${order.id}/bill-split`}]">
    <div class="max-w-5xl mx-auto p-6 space-y-8">
      <h1 class="text-2xl font-semibold text-primary">Bill Split for Order #{{ order.id }}</h1>
      <div class="grid md:grid-cols-2 gap-6">
        <div>
          <h2 class="font-semibold mb-2 text-primary">Order Items</h2>
          <table class="w-full text-sm border">
            <thead class="bg-primary/10">
              <tr>
                <th class="p-2 text-left">Item</th>
                <th class="p-2">Qty</th>
                <th class="p-2">Price</th>
                <th class="p-2">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="it in order.items" :key="it.id" class="border-t">
                <td class="p-2">{{ it.name }}</td>
                <td class="p-2 text-center">{{ it.quantity }}</td>
                <td class="p-2 text-right">{{ it.price }}</td>
                <td class="p-2 text-right">{{ it.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div>
          <h2 class="font-semibold mb-2 text-primary flex items-center justify-between">
            Participants
            <button @click="addParticipant" class="text-xs px-2 py-1 border rounded border-primary hover:bg-primary/10">Add</button>
          </h2>
          <div class="space-y-4">
            <div v-for="(p,pi) in participants" :key="pi" class="border rounded p-3">
              <div class="flex justify-between items-center mb-2">
                <input v-model="p.name" class="border p-1 text-sm flex-1 mr-2" />
                <button v-if="participants.length>1" @click="removeParticipant(pi)" class="text-red-500 text-xs">Remove</button>
              </div>
              <div class="flex flex-wrap gap-2">
                <button v-for="it in order.items" :key="it.id" @click="toggleItem(pi,it.id)" class="text-xs px-2 py-1 rounded border"
                  :class="p.items.includes(it.id) ? 'bg-primary text-[#f5f5dc] border-primary' : 'border-primary/40 text-primary'">
                  {{ it.name }}
                </button>
              </div>
              <div class="mt-2 text-xs text-primary/70">Base Share: {{ baseShares[pi] }} | Total with Tip: {{ shareTotals[pi] }}</div>
            </div>
          </div>
        </div>
      </div>
      <div class="grid md:grid-cols-3 gap-6">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-primary">Tip Percent (%)</label>
          <input type="number" v-model.number="tipPercent" class="border p-2 w-full" placeholder="e.g. 10" />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-primary">Or Tip Amount</label>
          <input type="number" v-model.number="tipAmount" class="border p-2 w-full" placeholder="e.g. 120" />
        </div>
        <div class="space-y-2 text-sm">
          <p>Total Before Tip: <strong>{{ totalBeforeTip.toFixed(2) }}</strong></p>
          <p>Tip: <strong>{{ computedTipAmount.toFixed(2) }}</strong></p>
          <p>Total After Tip: <strong>{{ (totalBeforeTip + computedTipAmount).toFixed(2) }}</strong></p>
        </div>
      </div>
      <div class="flex justify-end">
        <button @click="submit" :disabled="submitting" class="px-4 py-2 bg-primary text-[#f5f5dc] rounded disabled:opacity-50">{{ submitting? 'Saving...' : 'Save Split' }}</button>
      </div>
    </div>
  </AppLayout>
</template>
