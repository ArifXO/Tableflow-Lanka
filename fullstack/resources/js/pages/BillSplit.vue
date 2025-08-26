<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface OrderItem { id:number; quantity:number; price:number; dish:{ bn:string } }
interface Order { id:number; total_amount:number; order_items:OrderItem[] }
interface Payment { id:number; subtotal:string; tip_amount:string; total_amount:string; status:string }

const props = defineProps<{ order:Order; payments:Payment[] }>();

const selectedItems = ref<{order_item_id:number; shareQuantity:number; shareAmount:number}[]>([]);
const tipPercent = ref(10);
const guests = ref<{name:string; amount:number}[]>([{name:'You', amount:0}]);

const orderItems = computed(()=> props.order.order_items.map(i=> ({
  id:i.id,
  name:i.dish.bn,
  price: Number(i.price),
  quantity: i.quantity,
  maxAmount: Number(i.price) * i.quantity
})));

const subtotal = computed(()=> selectedItems.value.reduce((s,i)=> s + i.shareAmount,0));
const tipAmount = computed(()=> +(subtotal.value * (tipPercent.value/100)).toFixed(2));
const total = computed(()=> +(subtotal.value + tipAmount.value).toFixed(2));

function toggleItem(itemId:number, price:number) {
  const idx = selectedItems.value.findIndex(i=> i.order_item_id===itemId);
  if (idx>=0) { selectedItems.value.splice(idx,1); return; }
  selectedItems.value.push({order_item_id:itemId, shareQuantity:1, shareAmount:price});
}
function updateQuantity(itemId:number, quantity:number, price:number){
  const entry = selectedItems.value.find(i=> i.order_item_id===itemId);
  if(!entry) return;
  entry.shareQuantity = quantity;
  entry.shareAmount = +(price*quantity).toFixed(2);
}

function rebalanceGuests(){
  const even = total.value / guests.value.length;
  guests.value = guests.value.map(g=> ({...g, amount: +even.toFixed(2)}));
}

rebalanceGuests();

function addGuest(){ guests.value.push({name:`Guest ${guests.value.length+1}`, amount:0}); rebalanceGuests(); }
function removeGuest(i:number){ guests.value.splice(i,1); if(!guests.value.length) guests.value.push({name:'You',amount:total.value}); rebalanceGuests(); }

async function submit(){
  const res = await fetch(`/orders/${props.order.id}/bill-split`, {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')||''},
    body: JSON.stringify({
      items: selectedItems.value.map(i=> ({ order_item_id:i.order_item_id, quantity:i.shareQuantity, shareAmount:i.shareAmount })),
      tip_percent: tipPercent.value,
      guests: guests.value
    })
  });
  if(res.ok){ alert('Split saved'); location.reload(); } else { alert('Failed'); }
}
</script>

<template>
  <Head title="Bill Split" />
  <AppLayout :breadcrumbs="[{title:'Bill Split', href:`/orders/${order.id}/bill-split`}]">
    <div class="p-4 space-y-6">
      <h1 class="text-2xl font-semibold text-primary">Bill Split & Tip Calculator</h1>

      <div class="grid md:grid-cols-3 gap-6">
        <!-- Items -->
        <div class="md:col-span-2 space-y-4">
          <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded">
            <h2 class="font-semibold text-primary mb-3">Select Items</h2>
            <table class="w-full text-sm">
              <thead class="text-left text-xs text-primary/60">
                <tr><th>Item</th><th>Qty</th><th>Price</th><th>Select</th><th class="w-32">Your Qty</th></tr>
              </thead>
              <tbody>
                <tr v-for="it in orderItems" :key="it.id" class="border-t border-primary/10">
                  <td>{{ it.name }}</td>
                  <td>{{ it.quantity }}</td>
                  <td>{{ (it.price*it.quantity).toFixed(2) }}</td>
                  <td><input type="checkbox" :checked="!!selectedItems.find(s=>s.order_item_id===it.id)" @change="toggleItem(it.id,it.price)" /></td>
                  <td>
                    <input v-if="selectedItems.find(s=>s.order_item_id===it.id)" type="number" min="1" :max="it.quantity" class="w-20 border rounded px-1 py-0.5" :value="selectedItems.find(s=>s.order_item_id===it.id)?.shareQuantity" @input="e=> updateQuantity(it.id, +(e.target as HTMLInputElement).value, it.price)" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded">
            <h2 class="font-semibold text-primary mb-3">Guests</h2>
            <div class="space-y-2">
              <div v-for="(g,i) in guests" :key="i" class="flex items-center gap-2">
                <input v-model="g.name" class="border rounded px-2 py-1 text-sm flex-1" />
                <input v-model.number="g.amount" type="number" step="0.01" class="border rounded px-2 py-1 w-28 text-sm" />
                <button v-if="i>0" @click="removeGuest(i)" class="text-red-600 text-xs">x</button>
              </div>
            </div>
            <div class="mt-3 flex gap-2">
              <button @click="addGuest" class="px-2 py-1 bg-primary text-white rounded text-xs">Add Guest</button>
              <button @click="rebalanceGuests" class="px-2 py-1 bg-primary/70 text-white rounded text-xs">Even Split</button>
            </div>
          </div>
        </div>

        <!-- Summary -->
        <div class="space-y-4">
          <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded space-y-2">
            <h2 class="font-semibold text-primary mb-2">Summary</h2>
            <div class="text-sm flex justify-between"><span>Subtotal</span><span>{{ subtotal.toFixed(2) }}</span></div>
            <div class="text-sm flex justify-between items-center gap-2"><span>Tip %</span><input type="number" min="0" max="100" v-model.number="tipPercent" class="w-20 border rounded px-2 py-1 text-sm" /></div>
            <div class="text-sm flex justify-between"><span>Tip</span><span>{{ tipAmount.toFixed(2) }}</span></div>
            <div class="font-semibold flex justify-between text-primary border-t border-primary/10 pt-2"><span>Total</span><span>{{ total.toFixed(2) }}</span></div>
            <button :disabled="!selectedItems.length" @click="submit" class="mt-3 w-full bg-green-600 text-white py-2 rounded text-sm disabled:opacity-50">Save Split</button>
          </div>

          <div class="bg-[#fcfcf2] p-4 border border-primary/20 rounded">
            <h2 class="font-semibold text-primary mb-2 text-sm">Existing Splits</h2>
            <div v-if="!payments.length" class="text-xs text-primary/60">None yet.</div>
            <ul v-else class="text-xs space-y-1">
              <li v-for="p in payments" :key="p.id" class="flex justify-between">#{{ p.id }} <span>{{ p.total_amount }}</span></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
