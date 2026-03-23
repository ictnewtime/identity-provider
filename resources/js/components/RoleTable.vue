<script setup>
import { ref, reactive, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Paginator from "primevue/paginator";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

import RoleForm from "./RoleForm.vue";
import { Icon } from "@iconify/vue";
import { formatDate } from "../utils/data";

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const selectedRole = ref(null);
const displayDeleteModal = ref(false);
const roleToDelete = ref(null);
let searchTimeout = null;
const tableComponent = reactive({
    mainbar: {
        showRolesDeleted: false,
    },
});

const loadRoles = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/roles", {
            params: {
                page: page,
                per_page: pagination.value.per_page,
                q: filter.value,
                show_deleted: tableComponent.mainbar.showRolesDeleted,
            },
        })
        .then((res) => {
            pagination.value = res.data;
        })
        .catch((err) => {
            console.error(err);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.roles.toast.load_error"),
                life: 3000,
            });
            emit("item-error", err);
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadRoles(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadRoles(1);
    }, 500);
};

// Funzione esposta al padre
const openCreateModal = () => {
    selectedRole.value = null;
    displayModal.value = true;
};

defineExpose({
    openCreateModal,
});

const onRoleSaved = () => {
    displayModal.value = false;
    loadRoles();
};

const editRole = (role) => {
    selectedRole.value = role;
    displayModal.value = true;
};

const confirmDelete = (role) => {
    roleToDelete.value = role;
    displayDeleteModal.value = true;
};

const deleteRole = () => {
    if (!roleToDelete.value) return;

    window.axios
        .delete(`/admin/v1/roles/${roleToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            roleToDelete.value = null;
            loadRoles();
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.roles.toast.delete_success"),
                life: 3000,
            });
            emit("item-saved");
        })
        .catch((error) => {
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: error.response.data.message || trans("admin.roles.toast.delete_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};

const toggleShowRolesDeleted = () => {
    tableComponent.mainbar.showRolesDeleted = !tableComponent.mainbar.showRolesDeleted;
    loadRoles(1);
};

onMounted(() => {
    loadRoles();
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.roles.table.title") }}
                        </h3>
                        <div class="flex gap-4">
                            <Button
                                variant="text"
                                severity="danger"
                                @click="toggleShowRolesDeleted"
                                v-tooltip.top="$t('admin.roles.table.show_deleted_tooltip')"
                            >
                                <Icon icon="hugeicons:delete-put-back" width="24" height="24" />
                            </Button>
                            <IconField iconPosition="left">
                                <InputIcon class="pi pi-search text-surface-400" />
                                <InputText
                                    v-model="filter"
                                    :placeholder="$t('admin.roles.table.search_placeholder')"
                                    @input="onFilterChange"
                                    class="rounded-lg!"
                                />
                            </IconField>
                        </div>
                    </div>
                </template>

                <Column field="id" :header="$t('common.id')" style="width: 5%">
                    <template #body="slotProps">
                        <span class="text-surface-500 text-sm">{{ slotProps.data.id }}</span>
                    </template>
                </Column>

                <Column field="name" :header="$t('admin.roles.table.name')">
                    <template #body="slotProps">
                        <span class="font-bold text-surface-900">{{ slotProps.data.name }}</span>
                    </template>
                </Column>

                <Column :header="$t('admin.roles.table.provider')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.provider" class="text-surface-700 font-medium">
                            {{ slotProps.data.provider.name }}
                        </span>
                        <span v-else class="text-surface-400 italic">
                            {{ $t("admin.roles.table.missing_provider") }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('admin.roles.table.domain')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.provider" class="text-surface-600">
                            {{ slotProps.data.provider.domain }}
                        </span>
                        <span v-else class="text-surface-400 italic">
                            {{ $t("admin.roles.table.missing_domain") }}
                        </span>
                    </template>
                </Column>

                <Column
                    field="deleted_at"
                    :header="$t('admin.roles.table.deleted_at')"
                    v-if="tableComponent.mainbar.showRolesDeleted === true"
                >
                    <template #body="slotProps">
                        <span class="text-surface-600">{{ formatDate(slotProps.data.deleted_at) }}</span>
                    </template>
                </Column>

                <Column :header="$t('common.actions')" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-pencil"
                            text
                            rounded
                            severity="warn"
                            class="mr-1 hover:!bg-orange-50"
                            @click="editRole(slotProps.data)"
                        />
                        <Button
                            icon="pi pi-trash"
                            text
                            rounded
                            severity="danger"
                            class="hover:!bg-red-50"
                            @click="confirmDelete(slotProps.data)"
                        />
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-8 text-surface-500">
                        <i class="pi pi-id-card text-4xl mb-4 text-surface-300"></i>
                        <p class="m-0">
                            {{ $t("admin.roles.table.empty") }}
                        </p>
                    </div>
                </template>
            </DataTable>

            <Paginator
                v-if="pagination.total > 0"
                :rows="pagination.per_page"
                :totalRecords="pagination.total"
                @page="onPage"
                class="mt-4 border-t border-surface-100 pt-4"
            />
        </div>

        <Dialog
            v-model:visible="displayModal"
            :header="selectedRole ? $t('admin.roles.form.title_edit') : $t('admin.roles.form.title_create')"
            :style="{ width: '60vw', maxWidth: '800px' }"
            modal
            :draggable="false"
        >
            <RoleForm :selectedRole="selectedRole" @item-saved="onRoleSaved" />
        </Dialog>

        <Dialog
            v-model:visible="displayDeleteModal"
            :header="$t('common.confirm_delete_title')"
            :style="{ width: '450px' }"
            modal
            :draggable="false"
        >
            <div class="flex items-center gap-4 pt-2">
                <i class="pi pi-exclamation-triangle text-red-500 text-4xl"></i>
                <span v-if="roleToDelete" class="text-surface-700">
                    {{ $t("admin.roles.delete.prompt") }}
                    <b class="text-surface-900">{{ roleToDelete.name }}</b
                    >?
                </span>
            </div>

            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" @click="displayDeleteModal = false" text />
                <Button
                    :label="$t('common.delete')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteRole"
                    autofocus
                />
            </template>
        </Dialog>
    </div>
</template>
