<template>
  <div class="bg-cream rounded-lg shadow-sm border border-primary/10 flex flex-col card-hover w-full min-w-0">
    <img :src="dish.img" :alt="dish.en" class="w-full h-24 object-cover rounded-t-lg" />

    <div class="p-3 grow">
      <p class="font-semibold text-sm text-primary">{{ dish.bn }}</p>
      <p class="text-xs text-primary/80">{{ dish.en }}</p>
    </div>

    <div class="px-3 pb-3 flex items-center justify-between">
      <span class="font-medium text-sm text-primary">৳{{ dish.price }}</span>
      <button
        @click="$emit('add-to-basket', dish)"
        class="bg-[#3b1010] text-[#f5f5dc] px-2 py-1 rounded text-xs hover:opacity-90 focus:ring-2 focus:ring-primary/60 font-medium"
      >
        Add
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ dish: { id: number; cat: string; img: string; bn: string; en: string; price: number } }>();
defineEmits<{ 'add-to-basket': [dish: { id: number; cat: string; img: string; bn: string; en: string; price: number }] }>();
</script>
