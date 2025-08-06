<template>
  <div>
    <NavBar />
    <section class="hero">
      <div class="relative z-10 flex flex-col justify-center items-center h-full text-center">
        <img src="/images/logo.png" alt="লঙ্কা Logo" class="w-50 h-50 FadeInEntrance " />
        <h1 class="text-6xl md:text-8xl text-[#f5f5dc] font-ruposhi FadeInEntrance">লঙ্কা</h1>
      </div>
    </section>
    <section id="About" class="min-h-screen bg-cream flex items-center">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl md:text-4xl text-primary font-playfair mb-4">About লঙ্কা</h2>
       <p class="text-[#3b1010] leading-relaxed">Welcome to লঙ্কা, where we serve authentic Bengali flavors with a modern twist. Our chefs use the freshest ingredients to craft dishes that celebrate tradition and innovation.</p>
      </div>
    </section>
    <!-- Back-to-top arrow button -->
    <button @click="scrollToTop" class="fixed bottom-8 right-8 z-40 w-8 h-8 border border-[#3b1010] text-[#3b1010] flex items-center justify-center ">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 15l-7-7-7 7"/>
      </svg>
    </button>
  </div>
</template>

<script setup lang="ts">
import NavBar from '../components/NavBar.vue'

// Scroll to top handler
function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');
@import url('https://fonts.googleapis.com/css2?family=Ruposhi+Bangla&display=swap');

.font-playfair {
  font-family: 'Playfair Display', serif;
}

.font-ruposhi {
  font-family: 'Hind Siliguri', serif;
}

/* Hero section with dark overlay via pseudo-element */
.hero {
  position: relative;
  height: 100vh;
  background-image: url('/images/restaurant-bg.png');
  background-size: cover;
  background-position: center;
}
/* .hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
} */

@keyframes FadeInEntrance {
  from { opacity: 0; }
  to { opacity: 1; }
}

.FadeInEntrance {
  opacity: 0;
  animation: FadeInEntrance 2s ease-in forwards;
}

/* About section styles */
#About {
  background-color: #f5f5dc; /* cream color */
}

.text-primary {
  color: #3b1010; /* primary text color */
}
</style>
