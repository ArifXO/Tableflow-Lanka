<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import MenuCard from '@/components/MenuCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed} from 'vue';
import { ShoppingBasket } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

interface Dish {
  id: number;
  cat: string;
  img: string;
  bn: string;
  en: string;
  price: number;
}

interface BasketItem extends Dish {
  quantity: number;
}

const props = defineProps<{ dishes: Dish[] }>();
const categories = computed<string[]>(() => Array.from(new Set(props.dishes.map(d => d.cat))));
const active = ref<string>(categories.value[0] || '');
const filtered = computed(() => props.dishes.filter(d => d.cat === active.value));
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Menu', href: '/menu' }];

// Shopping basket state
const basketItems = ref<BasketItem[]>([]);
const basketCount = computed(() => basketItems.value.reduce((total, item) => total + item.quantity, 0));
const showBasket = ref(false);
const isSubmittingOrder = ref(false);

const addToBasket = (dish: Dish) => {
  const existingItem = basketItems.value.find(item => item.id === dish.id);

  if (existingItem) {
    existingItem.quantity += 1;
  } else {
    basketItems.value.push({ ...dish, quantity: 1 });
  }
};

const removeFromBasket = (index: number) => {
  const item = basketItems.value[index];
  if (item.quantity > 1) {
    item.quantity -= 1;
  } else {
    basketItems.value.splice(index, 1);
  }
};

const totalPrice = computed(() => {
  return basketItems.value.reduce((total, item) => total + (item.price * item.quantity), 0);
});

const potentialLoyaltyPoints = computed(() => {
  return Math.floor(totalPrice.value);
});

const submitOrder = async () => {
  if (basketItems.value.length === 0) return;

  isSubmittingOrder.value = true;

  try {
    const orderData = {
      items: basketItems.value.map(item => ({
        id: item.id,
        quantity: item.quantity
      })),
      notes: ''
    };
    // Helper to get current token from meta tag
    const readMetaToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let csrfToken = readMetaToken();
    let response = await fetch('/orders', {
      method: 'POST',
      credentials: 'same-origin', // ensure session cookie sent
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify(orderData)
    });

    // If CSRF mismatch (Laravel typically returns 419) try once to refresh token then retry
    if (response.status === 419) {
      try {
        const tokenResp = await fetch('/api/csrf-token', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        if (tokenResp.ok) {
          const tokenJson = await tokenResp.json();
          csrfToken = tokenJson.token;
          // update meta so subsequent requests work
          const meta = document.querySelector('meta[name="csrf-token"]');
          if (meta) meta.setAttribute('content', csrfToken);
          // retry request once
          response = await fetch('/orders', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(orderData)
          });
        }
      } catch {
        // ignore, will fall through to normal error handling
      }
    }

    let result: any = {};
    try { result = await response.json(); } catch { /* non-JSON response */ }

    if (response.ok) {
      basketItems.value = [];
      showBasket.value = false;
      const potentialPoints = result.potential_loyalty_points || potentialLoyaltyPoints.value;
      alert(`Order placed successfully! You'll earn ${potentialPoints} loyalty points when the order is completed.`);
      router.visit('/dashboard');
    } else if (response.status === 419) {
      throw new Error('Session expired. Please refresh the page and try again.');
    } else {
      throw new Error(result.message || 'Failed to place order');
    }
    } catch (error: any) {
    console.error('Order submission error:', error);
    alert(error?.message || 'Failed to place order. Please try again.');
  } finally {
    isSubmittingOrder.value = false;
  }
};
</script>

<template>
  <Head title="Menu" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Shopping Basket Button -->
    <div class="fixed top-16 right-4 z-[9999]">
      <button
        @click="showBasket = !showBasket"
        class="bg-primary text-[#f5f5dc] p-2 rounded-full shadow-lg hover:opacity-90 relative"
      >
        <ShoppingBasket :size="20" />
        <span
          v-if="basketCount > 0"
          class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium"
        >
          {{ basketCount }}
        </span>
      </button>
    </div>

    <!-- Shopping Basket Dropdown -->
    <div
      v-if="showBasket"
      class="fixed top-28 right-4 z-[9998] bg-[#fcfcf2] border border-primary/20 rounded-lg shadow-xl w-80 max-h-96 overflow-hidden flex flex-col"
    >
      <div class="p-4 border-b border-primary/10">
        <h3 class="font-semibold text-primary">Shopping Basket</h3>
      </div>

      <div v-if="basketItems.length === 0" class="p-4 text-center text-primary/60 flex-1">
        Your basket is empty
      </div>

      <div v-else class="flex-1 overflow-y-auto min-h-0">
        <div
          v-for="(item, index) in basketItems"
          :key="`${item.id}-${index}`"
          class="flex items-center justify-between p-3 border-b border-primary/5 hover:bg-primary/5"
        >
          <div class="flex-1">
            <p class="font-medium text-sm text-primary">{{ item.bn }}</p>
            <p class="text-xs text-primary/60">{{ item.en }}</p>
            <p class="text-sm font-medium text-primary">৳{{ item.price }} x {{ item.quantity }}</p>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="item.quantity > 1 ? item.quantity-- : removeFromBasket(index)"
              class="text-red-500 hover:text-red-700 text-xs px-2 py-1 border border-red-500 rounded"
            >
              {{ item.quantity > 1 ? '-' : 'Remove' }}
            </button>
            <span class="text-sm font-medium">{{ item.quantity }}</span>
            <button
              @click="item.quantity++"
              class="text-green-500 hover:text-green-700 text-xs px-2 py-1 border border-green-500 rounded"
            >
              +
            </button>
          </div>
        </div>
      </div>

      <div v-if="basketItems.length > 0" class="p-4 border-t border-primary/10 bg-primary/5 flex-shrink-0">
        <div class="flex justify-between items-center mb-2">
          <span class="font-semibold text-primary">Total:</span>
          <span class="font-bold text-primary">৳{{ totalPrice }}</span>
        </div>
        <div class="flex justify-between items-center mb-3 text-sm">
          <span class="text-primary/70">Potential Loyalty Points:</span>
          <span class="font-medium text-green-600">+{{ potentialLoyaltyPoints }} pts</span>
        </div>
        <button
          @click="submitOrder"
          :disabled="isSubmittingOrder"
          class="w-full bg-primary text-[#f5f5dc] py-2 rounded-md hover:opacity-90 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ isSubmittingOrder ? 'Placing Order...' : 'Place Order' }}
        </button>
      </div>
    </div>

    <div class="p-4">
      <!-- Tabs -->
      <div class="flex gap-6 mb-6">
        <button
          v-for="c in categories"
          :key="c"
          @click="active = c"
          class="pb-3 relative font-medium"
          :class="active === c ? 'text-primary' : 'text-primary/50'"
        >
          {{ c }}
          <span
            v-if="active === c"
            class="absolute -bottom-[1px] inset-x-0 h-[3px] bg-primary rounded-full"
          ></span>
        </button>
      </div>

      <!-- Cards -->
      <div class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4">
        <MenuCard
          v-for="d in filtered"
          :key="d.id"
          :dish="d"
          @add-to-basket="addToBasket"
        />
      </div>
    </div>
  </AppLayout>
</template>
