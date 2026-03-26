<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import ProviderUserRoleTable from "../../components/ProviderUserRoleTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    providerUserRoles: Array,
});

// Creiamo un riferimento per agganciarci al componente figlio
const tableRef = ref(null);

// Quando clicchiamo il bottone nel padre, diciamo al figlio di aprire la modale
const handleNewProviderUserRolesClick = () => {
    tableRef.value?.openCreateModal();
};
</script>

<template>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-surface-900 tracking-tight">
                {{ $t("admin.provider_user_roles.title") }}
            </h1>
        </div>
        <Button
            :label="$t('admin.provider_user_roles.new_providerUserRole')"
            icon="pi pi-plus"
            @click="handleNewProviderUserRolesClick"
        />
    </div>

    <ProviderUserRoleTable ref="tableRef" :providerUserRoles="providerUserRoles" />
</template>
