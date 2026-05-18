<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import UserTable from "../../components/UserTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    users: Array,
});

const isButtonAddRoleDisabled = ref(true);

const handleSelectionChange = (hasUsersSelected) => {
    isButtonAddRoleDisabled.value = !hasUsersSelected;
};

const tableRef = ref(null);

const handleNewUserClick = () => {
    tableRef.value?.openCreateModal();
};
const handleAddRoleClick = () => {
    tableRef.value?.openAddRoleModal();
};
</script>

<template>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-surface-900 tracking-tight" data-cy="page-title-users">
                {{ $t("admin.users.title") }}
            </h1>
        </div>
        <div class="flex gap-2">
            <Button
                :disabled="isButtonAddRoleDisabled"
                :label="$t('admin.users.add_role_relations')"
                icon="pi pi-plus"
                @click="handleAddRoleClick"
                class="shadow-sm"
                data-cy="btn-add-role-relations"
            />
            <Button
                :label="$t('admin.users.new_user')"
                icon="pi pi-plus"
                @click="handleNewUserClick"
                class="shadow-sm"
                data-cy="btn-new-user"
            />
        </div>
    </div>

    <UserTable
        ref="tableRef"
        :users="users"
        @selection-changed="handleSelectionChange"
        data-cy="users-table-component"
    />
</template>
