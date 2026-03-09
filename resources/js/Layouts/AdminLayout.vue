<script setup>
import { ref, onMounted, computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Menubar from "primevue/menubar";
import Button from "primevue/button";
import Select from "primevue/select";
import { trans } from "laravel-vue-i18n";

// Definizione delle voci della Navbar
const items = computed(() => [
    {
        label: trans("users"),
        icon: "pi pi-users",
        route: "/admin/users",
    },
    {
        label: trans("roles"),
        icon: "pi pi-id-card",
        route: "/admin/roles",
    },
    {
        label: trans("providers"),
        icon: "pi pi-server",
        route: "/admin/providers",
    },
    {
        label: trans("admin.nav.provider_user_roles"),
        icon: "pi pi-server",
        route: "/admin/provider-user-roles",
    },
    {
        label: trans("sessions"),
        icon: "pi pi-history",
        route: "/admin/sessions",
    },
]);

// Configurazione lingue
const selectedLocale = ref();
const locales = ref([
    { name: "Italiano", code: "it", flag: "🇮🇹" },
    { name: "English", code: "en", flag: "🇺🇸" },
]);

// Sincronizziamo la lingua visualizzata con quella attuale di Laravel
onMounted(() => {
    const currentLocale = usePage().props.locale || "it";
    selectedLocale.value = locales.value.find((l) => l.code === currentLocale);
});

// Funzione per cambiare lingua
const changeLanguage = (event) => {
    // Chiamiamo la rotta che abbiamo nel web.php
    // Usiamo window.location invece di router.get per forzare il refresh
    // e ricaricare tutti i file di traduzione correttamente
    window.location.href = `/locale/${event.value.code}`;
};

// Metodo di logout pulito tramite Inertia
const logout = () => {
    // Peschiamo il token aggiornato dalle props di Inertia!
    const token = usePage().props.csrf_token;

    router.post("/logout", {
        // Puoi passarlo direttamente nel payload (Inertia lo mappa sul _token di Laravel)
        _token: token,
    });
};
</script>

<template>
    <div class="min-h-screen bg-surface-50">
        <Menubar :model="items" class="px-6 py-2 border-b border-surface-200 rounded-none bg-surface-0">
            <template #start>
                <Link href="/admin/users" class="mr-8">
                    <img src="/images/logo.png" alt="Logo" class="h-8" />
                </Link>
            </template>

            <template #item="{ item, props }">
                <Link v-if="item.route" :href="item.route" v-bind="props.action">
                    <span :class="item.icon" class="p-menuitem-icon" />
                    <span class="p-menuitem-text">{{ item.label }}</span>
                </Link>
                <a v-else :href="item.url" :target="item.target" v-bind="props.action">
                    <span :class="item.icon" class="p-menuitem-icon" />
                    <span class="p-menuitem-text">{{ item.label }}</span>
                </a>
            </template>

            <template #end>
                <div class="flex items-center gap-4">
                    <Select
                        v-model="selectedLocale"
                        :options="locales"
                        optionLabel="name"
                        placeholder="Lingua"
                        class="w-40 h-10 items-center"
                        @change="changeLanguage"
                    >
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                <span>{{ slotProps.value.flag }}</span>
                                <span>{{ slotProps.value.name }}</span>
                            </div>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center gap-2">
                                <span>{{ slotProps.option.flag }}</span>
                                <span>{{ slotProps.option.name }}</span>
                            </div>
                        </template>
                    </Select>

                    <span class="text-surface-600 font-medium ml-2">
                        {{ $page.props.auth?.user?.username }}
                    </span>
                    <Button icon="pi pi-sign-out" severity="danger" text rounded @click="logout" />
                </div>
            </template>
        </Menubar>

        <main class="max-w-7xl mx-auto p-6">
            <slot />
        </main>
    </div>
</template>
