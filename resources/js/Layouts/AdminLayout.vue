<script setup>
import { ref, onMounted, computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
import Select from "primevue/select";
import { trans } from "laravel-vue-i18n";

const isSidebarCollapsed = ref(false);

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
};

const items = computed(() => [
    {
        label: trans("admin.nav.users"),
        icon: "pi pi-users",
        route: "/admin/users",
    },
    {
        label: trans("admin.nav.roles"),
        icon: "pi pi-id-card",
        route: "/admin/roles",
    },
    {
        label: trans("admin.nav.providers"),
        icon: "pi pi-server",
        route: "/admin/providers",
    },
    {
        label: trans("admin.nav.provider_user_roles"),
        icon: "pi pi-link",
        route: "/admin/provider-user-roles",
    },
    {
        label: trans("admin.nav.sessions"),
        icon: "pi pi-sitemap",
        route: "/admin/sessions",
    },
    {
        label: trans("admin.nav.audits"),
        icon: "pi pi-history",
        route: "/admin/audits",
    },
    {
        label: trans("admin.nav.parameters"),
        icon: "pi pi-cog",
        route: "/admin/parameters",
    },
]);

const selectedLocale = ref();

onMounted(() => {
    selectedLocale.value = "it";
});

const logout = () => {
    const token = usePage().props.csrf_token;
    router.post("/logout", {
        _token: token,
    });
};
</script>

<template>
    <div class="flex h-screen bg-slate-100 overflow-hidden font-sans text-surface-900 antialiased">
        <notification />
        <aside
            :class="[
                'bg-white flex flex-col flex-shrink-0 z-20 shadow-[4px_0_24px_rgba(0,0,0,0.04)] transition-all duration-300 ease-in-out relative',
                isSidebarCollapsed ? 'w-[80px]' : 'w-[280px]',
            ]"
        >
            <div class="h-[72px] flex items-center justify-between px-4">
                <Link
                    v-if="!isSidebarCollapsed"
                    href="/admin/users"
                    class="flex items-center gap-3 overflow-hidden ml-2"
                >
                    <img src="/images/logo.png" alt="Logo" class="h-8 object-contain drop-shadow-sm" />
                </Link>

                <Button
                    icon="pi pi-bars"
                    text
                    rounded
                    class="!text-surface-600 hover:!bg-surface-100 shrink-0"
                    :class="isSidebarCollapsed ? 'mx-auto' : ''"
                    @click="toggleSidebar"
                />
            </div>

            <nav class="flex-1 overflow-y-auto p-3 flex flex-col gap-1.5 mt-2">
                <Link
                    v-for="item in items"
                    :key="item.route"
                    :href="item.route"
                    v-tooltip.right="isSidebarCollapsed ? item.label : ''"
                    class="group flex items-center px-3 py-3 rounded-xl transition-all duration-200 cursor-pointer"
                    :class="[
                        $page.url.startsWith(item.route)
                            ? 'bg-primary-600 font-bold shadow-md shadow-primary-500/30'
                            : 'text-surface-600 font-medium hover:bg-surface-100 hover:text-surface-900',
                        isSidebarCollapsed ? 'justify-center' : 'gap-3',
                    ]"
                >
                    <i
                        :class="[
                            item.icon,
                            'text-xl transition-colors',
                            $page.url.startsWith(item.route) ? '' : 'text-surface-400 group-hover:text-surface-600',
                        ]"
                    ></i>
                    <span v-if="!isSidebarCollapsed" class="mt-0.5 whitespace-nowrap">{{ item.label }}</span>
                </Link>
            </nav>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden relative z-10">
            <header class="h-[72px] bg-white shadow-sm flex items-center justify-end px-6 gap-6 shrink-0 relative z-10">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3">
                        <span class="text-surface-900 font-bold text-sm">
                            {{ $page.props.auth?.user?.username }}
                        </span>
                    </div>

                    <Button
                        icon="pi pi-sign-out"
                        text
                        rounded
                        severity="secondary"
                        class="hover:!text-red-600 hover:!bg-red-50 transition-colors"
                        @click="logout"
                    />
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6 md:p-8 lg:p-10">
                <slot />
            </main>
        </div>
    </div>
</template>
