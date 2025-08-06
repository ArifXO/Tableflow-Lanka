<template>
  <div class="bg-cream rounded-lg border border-primary/10 p-4">
    <h3 class="text-lg font-medium text-primary mb-4">Table Availability</h3>

    <div v-if="loading" class="text-center py-8">
      <p class="text-primary/60">Loading table availability...</p>
    </div>

    <div v-else-if="error" class="text-center py-8">
      <p class="text-red-600">{{ error }}</p>
    </div>

    <div v-else-if="selectedDate && selectedTime" class="space-y-3">
      <div class="text-sm text-primary/80 mb-3">
        {{ selectedDate.toLocaleDateString('en-US', {
          weekday: 'long',
          month: 'long',
          day: 'numeric',
          year: 'numeric'
        }) }} at {{ selectedTime }}
      </div>

      <!-- Legend -->
      <div class="flex items-center gap-4 text-xs mb-4">
        <div class="flex items-center gap-1">
          <div class="w-3 h-3 bg-[#f5f5dc] border border-[#3b1010] rounded"></div>
          <span class="text-primary/70">Available</span>
        </div>
        <div class="flex items-center gap-1">
          <div class="w-3 h-3 bg-primary/10 border border-primary/20 rounded"></div>
          <span class="text-primary/70">Booked</span>
        </div>
        <div class="flex items-center gap-1">
          <div class="w-3 h-3 bg-[#3b1010] border border-[#3b1010] rounded"></div>
          <span class="text-primary/70">Your Selection</span>
        </div>
      </div>

      <!-- Tables Grid -->
      <div class="grid grid-cols-4 gap-2">
        <div
          v-for="table in tables"
          :key="table.id"
          :class="[
            'relative border rounded-lg p-3 text-center text-sm font-medium transition-all cursor-pointer',
            getTableClasses(table)
          ]"
          @click="selectTable(table)"
        >
          <div class="text-xs opacity-80 mb-1">Table</div>
          <div class="font-bold">{{ table.number }}</div>
          <div class="text-xs opacity-80 mt-1">{{ table.seats }} seats</div>

          <!-- Selection indicator -->
          <div
            v-if="selectedTable?.id === table.id"
            class="absolute -top-1 -right-1 w-4 h-4 bg-[#3b1010] text-[#f5f5dc] rounded-full flex items-center justify-center text-xs"
          >
            ✓
          </div>
        </div>
      </div>

      <!-- Selected table info -->
      <div v-if="selectedTable" class="mt-4 p-3 bg-primary/5 rounded-lg border border-primary/10">
        <h4 class="font-medium text-primary mb-2">Selected Table</h4>
        <div class="text-sm text-primary/80 space-y-1">
          <p><span class="font-medium">Table:</span> {{ selectedTable.number }}</p>
          <p><span class="font-medium">Capacity:</span> {{ selectedTable.seats }} seats</p>
          <p><span class="font-medium">Status:</span>
            <span :class="[
              'font-medium',
              !selectedTable.isAvailable ? 'text-red-600' : 'text-green-600'
            ]">
              {{ !selectedTable.isAvailable ? 'Booked' : 'Available' }}
            </span>
          </p>
        </div>
      </div>
    </div>

    <div v-else class="text-center text-primary/60 py-8">
      <p>Please select a date and time to view table availability</p>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';

interface Table {
  id: number;
  number: string;
  seats: number;
  isAvailable: boolean;
}

interface Props {
  selectedDate: Date | null;
  selectedTime: string | null;
  partySize: number;
  reservationConfirmed: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  tableSelected: [table: Table | null];
}>();

const selectedTable = ref<Table | null>(null);
const tables = ref<Table[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

// Convert time format from "4:00 PM" to "16:00"
const convertTimeFormat = (timeString: string): string => {
  const [time, period] = timeString.split(' ');
  const [hours, minutes] = time.split(':');
  let hour24 = parseInt(hours);

  if (period === 'PM' && hour24 !== 12) {
    hour24 += 12;
  } else if (period === 'AM' && hour24 === 12) {
    hour24 = 0;
  }

  return `${hour24.toString().padStart(2, '0')}:${minutes}`;
};

// Fetch table availability from API
const fetchTableAvailability = async (forceRefresh = false) => {
  if (!props.selectedDate || !props.selectedTime) {
    tables.value = [];
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    const params: any = {
      date: props.selectedDate.toISOString().split('T')[0],
      time: convertTimeFormat(props.selectedTime)
    };

    // Add timestamp to force refresh
    if (forceRefresh) {
      params.timestamp = Date.now();
    }

    const response = await axios.get('/reservation/availability', { params });

    tables.value = response.data.tables.map((table: any) => ({
      id: table.id,
      number: table.number,
      seats: table.seats,
      isAvailable: table.isAvailable
    }));
  } catch (err: any) {
    console.error('Error fetching table availability:', err);
    error.value = err.response?.data?.error || 'Failed to fetch table availability';
    tables.value = [];
  } finally {
    loading.value = false;
  }
};

const getTableClasses = (table: Table) => {
  if (selectedTable.value?.id === table.id) {
    return 'bg-[#3b1010] text-[#f5f5dc] border-[#3b1010] shadow-lg scale-105';
  } else if (!table.isAvailable) {
    return 'bg-primary/10 text-primary border-primary/20 cursor-not-allowed opacity-75';
  } else {
    return 'bg-[#f5f5dc] text-[#3b1010] border-[#3b1010] hover:bg-[#f5f5dc]/80 hover:scale-105';
  }
};

const selectTable = (table: Table) => {
  if (!table.isAvailable) return;

  if (selectedTable.value?.id === table.id) {
    selectedTable.value = null;
  } else {
    selectedTable.value = table;
  }

  emit('tableSelected', selectedTable.value);
};

// Watch for changes and fetch availability
watch([() => props.selectedDate, () => props.selectedTime], () => {
  // Reset selection when date/time changes
  selectedTable.value = null;
  emit('tableSelected', null);
  // Fetch new availability
  fetchTableAvailability();
}, { immediate: true });

// Watch for reservation confirmation to refresh data
watch(() => props.reservationConfirmed, (newVal, oldVal) => {
  if (newVal && !oldVal) {
    // Reservation was just confirmed, refresh table availability
    setTimeout(() => {
      fetchTableAvailability(true); // Force refresh
    }, 500); // Small delay to ensure backend is updated
  }
});

// Expose refresh method for parent component
defineExpose({
  refreshAvailability: () => fetchTableAvailability(true)
});

// Auto-select suitable table based on party size
watch(() => props.partySize, (newSize) => {
  if (selectedTable.value && newSize > selectedTable.value.seats) {
    // Current table is too small, find a suitable one
    const suitableTable = tables.value.find(table =>
      table.isAvailable && table.seats >= newSize
    );

    if (suitableTable) {
      selectedTable.value = suitableTable;
      emit('tableSelected', selectedTable.value);
    } else {
      selectedTable.value = null;
      emit('tableSelected', null);
    }
  }
});
</script>
