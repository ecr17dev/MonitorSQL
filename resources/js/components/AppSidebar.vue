<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Database, HardDrive, HardDriveDownload, History, MessageSquare, ShieldCheck, Users } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { chat } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const permissions = computed<string[]>(() => {
    return (page.props.auth?.permissions as string[] | undefined) ?? [];
});

const canViewConnections = computed<boolean>(() => permissions.value.includes('connections.view'));
const canViewAudit = computed<boolean>(() => permissions.value.includes('audit.view'));
const canRunQueries = computed<boolean>(() => permissions.value.includes('queries.execute'));
const canExportQueries = computed<boolean>(() => permissions.value.includes('queries.export'));
const canManagePlatform = computed<boolean>(() => permissions.value.includes('connections.create'));

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Chat',
            href: chat(),
            icon: MessageSquare,
        },
    ];

    if (canViewConnections.value) {
        items.push({
            title: 'Conexiones',
            href: '/connections',
            icon: Database,
        });
    }

    if (canRunQueries.value) {
        items.push({
            title: 'Historial de consultas',
            href: '/dashboard#query-history',
            icon: History,
        });
    }

    if (canViewAudit.value) {
        items.push({
            title: 'Auditoría',
            href: '/dashboard#audit',
            icon: ShieldCheck,
        });
    }

    if (canExportQueries.value) {
        items.push({
            title: 'Cola de exportación',
            href: '/exports/queue',
            icon: HardDriveDownload,
        });
    }

    if (canManagePlatform.value) {
        items.push({
            title: 'Usuarios / Roles / Permisos',
            href: '/admin/access-control',
            icon: Users,
        });
        items.push({
            title: 'Respaldos',
            href: '/backups',
            icon: HardDrive,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="chat()">
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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
