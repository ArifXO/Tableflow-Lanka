<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Calendar20 from '@/components/Calendar20.vue';
import TableAvailability from '@/components/TableAvailability.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

// Props from backend
interface Props {
    tables: Array<{
        id: number;
        number: string;
        seats: number;
        description: string;
        is_active: boolean;
    }>;
    timeSlots: string[];
    user?: {
        id: number;
        name: string;
        email: string;
    } | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reservation',
        href: '/reservations',
    },
];

// State management with dynamic defaults
const selectedDate = ref<Date | null>(null);
const selectedTime = ref<string | null>(null);
const partySize = ref(2); // Default to 2 people
const showPartySizeDropdown = ref(false);
const selectedTable = ref<any>(null);
const reservationConfirmed = ref(false);
const tableAvailabilityRef = ref();

// Computed property for available party sizes based on available tables
const maxPartySize = computed(() => {
    if (!props.tables || props.tables.length === 0) return 8; // Fallback
    return Math.max(...props.tables.map(table => table.seats));
});

// Generate party size options
const partySizeOptions = computed(() => {
    return Array.from({ length: Math.min(maxPartySize.value, 12) }, (_, i) => i + 1);
});

const handleCalendarBooking = (booking: { date: Date; time: string }) => {
    selectedDate.value = booking.date;
    selectedTime.value = booking.time;
    // Reset reservation confirmed when date/time changes
    reservationConfirmed.value = false;
};

const handleTableSelection = (table: any) => {
    selectedTable.value = table;
};

// Close dropdown when clicking outside
const closeDropdown = (event: Event) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.party-size-dropdown')) {
        showPartySizeDropdown.value = false;
    }
};

// Add event listener for clicking outside
if (typeof window !== 'undefined') {
    document.addEventListener('click', closeDropdown);
}

// Helper function for time conversion
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

const confirmReservation = async () => {
    if (selectedDate.value && selectedTime.value && selectedTable.value) {
        try {
            const response = await fetch('/reservation', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    table_id: selectedTable.value.id,
                    reservation_date: selectedDate.value.toISOString().split('T')[0],
                    reservation_time: convertTimeFormat(selectedTime.value),
                    party_size: partySize.value,
                    customer_name: props.user?.name || '',
                    customer_email: props.user?.email || ''
                })
            });

            const data = await response.json();

            if (response.ok) {
                const dateStr = selectedDate.value.toLocaleDateString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric'
                });

                alert(`🎉 Reservation confirmed!\n📅 ${dateStr} at ${selectedTime.value}\n👥 ${partySize.value} ${partySize.value === 1 ? 'guest' : 'guests'}\n🪑 Table ${selectedTable.value.number}`);

                reservationConfirmed.value = true;

                // Refresh table availability
                if (tableAvailabilityRef.value?.refreshAvailability) {
                    setTimeout(() => {
                        tableAvailabilityRef.value.refreshAvailability();
                    }, 1000);
                }

                // Reset for next reservation
                selectedTable.value = null;
            } else {
                if (data.errors) {
                    const errorMessages = Object.values(data.errors).flat();
                    alert('❌ Validation Error:\n' + errorMessages.join('\n'));
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to create reservation. Please try again.'));
                }
            }
        } catch (error) {
            console.error('Reservation error:', error);
            alert('Failed to create reservation. Please try again.');
        }
    } else {
        const missingFields = [];
        if (!selectedDate.value) missingFields.push('date');
        if (!selectedTime.value) missingFields.push('time');
        if (!selectedTable.value) missingFields.push('table');

        alert(`Please provide the following: ${missingFields.join(', ')}`);
    }
};
</script>

<template>
    <Head title="Reservation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="bg-cream min-h-screen">
            <!-- Main Content -->
            <div class="max-w-7xl mx-auto p-6 pb-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- LEFT COLUMN: Calendar & Summary -->
                    <div class="space-y-6">
                        <!-- Calendar Section with Party Size -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-semibold text-primary">Select Date & Time</h2>
                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-primary">Party Size:</label>
                                    <div class="relative party-size-dropdown">
                                        <button
                                            @click="showPartySizeDropdown = !showPartySizeDropdown"
                                            class="bg-primary text-[#f5f5dc] px-3 py-1 rounded-md shadow-lg hover:opacity-90 text-sm font-medium min-w-[50px] flex items-center gap-1"
                                        >
                                            {{ partySize }}
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        <!-- Party Size Dropdown -->
                                        <div
                                            v-if="showPartySizeDropdown"
                                            class="absolute top-full mt-1 right-0 z-[9999] bg-[#fcfcf2] border border-primary/20 rounded-lg shadow-xl w-20 overflow-hidden"
                                        >
                                            <div class="py-1">
                                                <button
                                                    v-for="n in partySizeOptions"
                                                    :key="n"
                                                    @click="partySize = n; showPartySizeDropdown = false"
                                                    :class="[
                                                        'w-full px-3 py-2 text-center text-sm hover:bg-primary/5 transition-colors',
                                                        partySize === n ? 'bg-primary/10 text-primary font-medium' : 'text-primary/80'
                                                    ]"
                                                >
                                                    {{ n }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <Calendar20 @booked="handleCalendarBooking" />
                        </div>

                        <!-- Reservation Summary -->
                        <div v-if="selectedDate && selectedTime" class="bg-cream rounded-lg border border-primary/10 p-4">
                            <h3 class="text-lg font-medium text-primary mb-3">Reservation Summary</h3>
                            <div class="space-y-2 text-primary/80">
                                <p><span class="font-medium">Date:</span> {{ selectedDate.toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                }) }}</p>
                                <p><span class="font-medium">Time:</span> {{ selectedTime }}</p>
                                <p><span class="font-medium">Party Size:</span> {{ partySize }} {{ partySize === 1 ? 'guest' : 'guests' }}</p>
                                <p v-if="selectedTable"><span class="font-medium">Table:</span> {{ selectedTable.number }} ({{ selectedTable.seats }} seats)</p>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Table Availability & Confirm Button -->
                    <div class="space-y-6">
                        <!-- Table Availability -->
                        <TableAvailability
                            ref="tableAvailabilityRef"
                            :selected-date="selectedDate"
                            :selected-time="selectedTime"
                            :party-size="partySize"
                            :reservation-confirmed="reservationConfirmed"
                            @table-selected="handleTableSelection"
                        />

                        <!-- Confirm Button -->
                        <div>
                            <button
                                @click="confirmReservation"
                                :disabled="!selectedDate || !selectedTime || !selectedTable"
                                :class="[
                                    'px-8 py-3 rounded-lg font-semibold text-lg transition-all w-full',
                                    selectedDate && selectedTime && selectedTable
                                        ? 'bg-primary text-[#f5f5dc] hover:bg-primary/90 shadow-lg hover:shadow-xl'
                                        : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                ]"
                            >
                                Confirm Reservation
                            </button>

                            <p v-if="selectedDate && selectedTime && !selectedTable"
                               class="text-center text-sm text-primary/60 mt-2">
                                Please select a table to continue
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
