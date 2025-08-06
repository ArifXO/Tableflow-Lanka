<template>
  <div class="bg-cream rounded-lg border border-primary/20 overflow-hidden shadow-lg max-w-2xl">
    <!-- Main Content -->
    <div class="relative md:pr-40">
      <div class="p-4">
        <!-- Calendar -->
        <div class="bg-transparent p-0">
          <!-- Calendar Header -->
          <div class="flex items-center justify-between mb-3">
            <button @click="previousMonth" class="p-2 hover:bg-primary/10 rounded">
              <ChevronLeft :size="20" class="text-primary" />
            </button>
            <h3 class="text-base font-semibold text-primary">
              {{ currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) }}
            </h3>
            <button @click="nextMonth" class="p-2 hover:bg-primary/10 rounded">
              <ChevronRight :size="20" class="text-primary" />
            </button>
          </div>

          <!-- Calendar Grid -->
          <div class="grid grid-cols-7 gap-2 mb-2">
            <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
                 :key="day"
                 class="text-center text-sm font-medium text-primary/60 p-2">
              {{ day }}
            </div>
          </div>

          <!-- Calendar Days -->
          <div class="grid grid-cols-7 gap-2">
            <button
              v-for="(day, index) in calendarDays"
              :key="index"
              :class="[
                'p-2 text-sm rounded-lg transition-colors h-10 w-10',
                getDayClasses(day)
              ]"
              @click="day && selectDate(day)"
              :disabled="!day || isBooked(day) || isPastDate(day)"
            >
              <span :class="{ 'line-through': day && isBooked(day) }">
                {{ day?.getDate() }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Time Slots Panel -->
      <div class="max-h-52 w-full flex flex-col gap-2 overflow-y-auto border-t p-4 md:absolute md:inset-y-0 md:right-0 md:max-h-none md:w-40 md:border-t-0 md:border-l">
        <div class="grid gap-2">
          <button
            v-for="time in timeSlots"
            :key="time"
            :class="[
              'w-full py-2 px-2 text-sm rounded-md transition-colors',
              selectedTime === time
                ? 'bg-primary text-[#f5f5dc] font-medium border border-primary/20'
                : 'bg-[#fcfcf2] border border-primary/20 text-primary/80 hover:bg-primary/5'
            ]"
            @click="selectedTime = time"
          >
            {{ time }}
          </button>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="flex flex-col gap-2 border-t px-4 py-4 md:flex-row">
      <div class="text-sm text-primary">
        <span v-if="selectedDate && selectedTime">
          Selected:
          <span class="font-medium">
            {{ selectedDate.toLocaleDateString('en-US', {
              weekday: 'short',
              month: 'short',
              day: 'numeric'
            }) }}
          </span>
          at <span class="font-medium">{{ selectedTime }}</span>
        </span>
        <span v-else>
          Select a date and time for your reservation.
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

// Props
interface BookingEvent {
  date: Date;
  time: string;
}

// Emits
const emit = defineEmits<{
  booked: [booking: BookingEvent]
}>();

// State
const selectedDate = ref<Date | null>(new Date()); // Current date
const selectedTime = ref<string | null>("4:00 PM");
const currentDate = ref(new Date()); // Current month/year

// Time slots (4:00 PM to 10:00 PM in 1-hour intervals)
const timeSlots = computed(() => {
  return Array.from({ length: 7 }, (_, i) => {
    const hour = i + 16; // Start from 4 PM (16:00)

    // Convert to 12-hour format
    const displayHour = hour > 12 ? hour - 12 : hour;
    const ampm = hour >= 12 ? 'PM' : 'AM';

    return `${displayHour}:00 ${ampm}`;
  });
});

// Booked dates (example: August 17-19, 2025)
const bookedDates = [
  new Date(2025, 7, 17),
  new Date(2025, 7, 18),
  new Date(2025, 7, 19)
];

// Calendar computation
const calendarDays = computed(() => {
  const year = currentDate.value.getFullYear();
  const month = currentDate.value.getMonth();

  const firstDay = new Date(year, month, 1);
  const startDate = new Date(firstDay);
  startDate.setDate(firstDay.getDate() - firstDay.getDay()); // Start from Sunday

  const days: (Date | null)[] = [];
  const current = new Date(startDate);

  // Add 42 days (6 weeks) to fill the calendar grid
  for (let i = 0; i < 42; i++) {
    if (current.getMonth() === month) {
      days.push(new Date(current));
    } else {
      days.push(null); // Outside current month
    }
    current.setDate(current.getDate() + 1);
  }

  return days;
});

// Watch for changes and emit to parent
watch([selectedDate, selectedTime], () => {
  if (selectedDate.value && selectedTime.value) {
    emit('booked', {
      date: selectedDate.value,
      time: selectedTime.value
    });
  }
}, { immediate: true });

// Methods
const selectDate = (date: Date) => {
  if (!isBooked(date) && !isPastDate(date)) {
    selectedDate.value = date;
  }
};

const isBooked = (date: Date | null): boolean => {
  if (!date) return false;
  return bookedDates.some(bookedDate =>
    bookedDate.toDateString() === date.toDateString()
  );
};

const isPastDate = (date: Date | null): boolean => {
  if (!date) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0); // Reset time to start of day
  const checkDate = new Date(date);
  checkDate.setHours(0, 0, 0, 0); // Reset time to start of day
  return checkDate < today;
};

const getDayClasses = (day: Date | null) => {
  if (!day) return 'invisible';

  const isSelected = selectedDate.value && day.toDateString() === selectedDate.value.toDateString();
  const isBookedDay = isBooked(day);
  const isToday = day.toDateString() === new Date().toDateString();
  const isPast = isPastDate(day);

  let classes = '';

  if (isSelected) {
    classes += 'bg-primary text-[#f5f5dc] font-bold';
  } else if (isPast) {
    classes += 'text-gray-400 opacity-50 cursor-not-allowed';
  } else if (isBookedDay) {
    classes += 'text-red-500 opacity-50 cursor-not-allowed';
  } else if (isToday) {
    classes += 'bg-primary/10 text-primary font-medium';
  } else {
    classes += 'text-primary hover:bg-primary/10';
  }

  return classes;
};

const previousMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1);
};

const nextMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1);
};
</script>
