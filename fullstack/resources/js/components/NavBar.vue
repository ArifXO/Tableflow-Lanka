<template>
  <nav class="sticky top-0 z-50 bg-[#3b1010]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-12">
      <div class="flex items-center space-x-1">
        <img src="/images/logo.png" alt="লঙ্কা Logo" class="w-6 h-6" />
        <span class="text-[#f5f5dc] text-lg ">লঙ্কা</span>
      </div>
      <div class="hidden md:flex space-x-6 text-[#f5f5dc] text-sm">
        <a href="#menu" class="hover:text-gray-300">Menu</a>
        <a href="#reservations" class="hover:text-gray-300">Reservations</a>
        <a href="#About" class="hover:text-gray-300">About</a>
      </div>
      <div class="flex space-x-2">
        <a href="/login" class="border border-[#f5f5dc] text-[#f5f5dc] text-sm px-3 py-1 rounded hover:bg-[#f5f5dc] hover:text-[#3b1010] transition-colors duration-150">Login</a>
        <a href="/register" class="border border-[#f5f5dc] text-[#f5f5dc] text-sm px-3 py-1 rounded hover:bg-[#f5f5dc] hover:text-[#3b1010] transition-colors duration-150">Register</a>
      </div>
    </div>
  </nav>
</template>

<script setup lang="ts">
// Static navbar component
</script>
