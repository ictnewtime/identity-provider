<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import ProviderUserRoleTable from "../../components/ProviderUserRoleTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    providerUserRoles: Array,
});

const tableRef = ref(null);

const handleNewProviderUserRolesClick = () => {
    tableRef.value?.openCreateModal();
};
</script>

<template>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-surface-900 tracking-tight" data-cy="page-title-provider-user-roles">
                {{ $t("admin.provider_user_roles.title") }}
            </h1>
        </div>
        <Button
            :label="$t('admin.provider_user_roles.new_providerUserRole')"
            icon="pi pi-plus"
            @click="handleNewProviderUserRolesClick"
            data-cy="btn-new-provider-user-role"
        />
    </div>

    <ProviderUserRoleTable
        ref="tableRef"
        :providerUserRoles="providerUserRoles"
        data-cy="provider-user-roles-table-component"
    />
</template>
