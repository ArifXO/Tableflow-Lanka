<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { LayoutGrid, Soup, CalendarSearch, Gift  } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage();
const role = computed(()=> (page.props.auth as any)?.user?.role || 'diner');

// Generate role-specific navigation to avoid redundant dashboard variants
const mainNavItems = computed<NavItem[]>(() => {
    const r = role.value;
    const items: NavItem[] = [];

    if (r === 'diner') {
        items.push({ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid });
        items.push({ title: 'Menu', href: '/menu', icon: Soup });
        items.push({ title: 'Reservation', href: '/reservation', icon: CalendarSearch });
        items.push({ title: 'Loyalty Points', href: '/loyalty-points', icon: Gift });
    } else if (r === 'kitchen') {
        items.push({ title: 'Kitchen Dashboard', href: '/dashboard/kitchen', icon: LayoutGrid });
    } else if (r === 'manager') {
        // Manager gets a single main dashboard plus optional quick link to kitchen ops
        items.push({ title: 'Manager Dashboard', href: '/dashboard/manager', icon: LayoutGrid });
        items.push({ title: 'Kitchen Ops', href: '/dashboard/kitchen', icon: LayoutGrid });
    } else {
        // Fallback
        items.push({ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid });
    }
    return items;
});

const footerNavItems: NavItem[] = [
    // Add footer navigation items here if needed
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
