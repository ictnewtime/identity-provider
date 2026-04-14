<script setup>
import { ref, reactive, onMounted, computed } from "vue";
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
import ProviderUserRoleForm from "./ProviderUserRoleForm.vue";
import { Icon } from "@iconify/vue";
import { formatDate } from "../utils/data";

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const itemSelected = ref(null);
const displayDeleteModal = ref(false);
const displayRestoreModal = ref(false);
const selectedProviderUserRoles = ref();
let searchTimeout = null;
const tableComponent = reactive({
    showRecordsDeleted: false,
});

const loadRecords = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/provider-user-roles", {
            params: {
                page: page,
                per_page: pagination.value.per_page,
                q: filter.value,
                show_deleted: tableComponent.showRecordsDeleted,
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
                detail: trans("admin.provider_user_roles.toast.load_error"),
                life: 3000,
            });
            emit("item-error", err);
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadRecords(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadRecords(1);
    }, 500);
};

const openCreateModal = () => {
    itemSelected.value = null;
    displayModal.value = true;
};

// Esponiamo la funzione al componente padre
defineExpose({
    openCreateModal,
});

const onItemSaved = () => {
    displayModal.value = false;
    loadRecords();
};

const editItem = (item) => {
    itemSelected.value = item;
    displayModal.value = true;
};

const confirmDelete = (item) => {
    itemSelected.value = item;
    displayDeleteModal.value = true;
};
const confirmRestore = (item) => {
    itemSelected.value = item;
    displayRestoreModal.value = true;
};

const toggleShowRecordsDeleted = () => {
    tableComponent.showRecordsDeleted = !tableComponent.showRecordsDeleted;
    selectedProviderUserRoles.value = [];
    loadRecords(1);
};

// --- FUNZIONI CORE (Gestiscono le chiamate API con array di ID) ---

const deleteProviderUserRoles = (ids) => {
    if (!ids || ids.length === 0) return;

    // Assicurati che il backend abbia una rotta che accetti { ids: [...] } nel payload
    window.axios
        .delete("/admin/v1/provider-user-roles/bulk-delete", { data: { ids } })
        .then(() => {
            displayDeleteModal.value = false;
            itemSelected.value = null;
            selectedProviderUserRoles.value = []; // Svuota la selezione della tabella
            loadRecords(pagination.value.current_page);
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.provider_user_roles.toast.delete_success"),
                life: 3000,
            });
            emit("item-saved");
        })
        .catch((error) => {
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.provider_user_roles.toast.delete_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};

const restoreProviderUserRoles = (ids) => {
    if (!ids || ids.length === 0) return;

    window.axios
        .patch("/admin/v1/provider-user-roles/bulk-restore", { ids })
        .then(() => {
            displayRestoreModal.value = false;
            itemSelected.value = null;
            selectedProviderUserRoles.value = [];
            loadRecords(pagination.value.current_page);
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.provider_user_roles.toast.restore_success"),
                life: 3000,
            });
            emit("item-saved");
        })
        .catch((error) => {
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.provider_user_roles.toast.restore_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};

// FUNZIONI COLLEGATE AI BOTTONI / MODALI DELL'UI

const deleteSelectedProviderUserRoles = () => {
    const rawData = getSelectedProviderUserRoles();
    const ids = rawData.map((item) => item.id);
    deleteProviderUserRoles(ids);
};

const restoreSelectedProviderUserRoles = () => {
    const rawData = getSelectedProviderUserRoles();
    const ids = rawData.map((item) => item.id);
    restoreProviderUserRoles(ids);
};

const getSelectedProviderUserRoles = () => {
    let rawData = [];
    try {
        rawData = JSON.parse(JSON.stringify(selectedProviderUserRoles.value));
    } catch (error) {
        console.error(error);
    }
    return rawData;
};

const hasSelectedProviderUserRoles = computed(() => {
    return selectedProviderUserRoles.value && selectedProviderUserRoles.value.length > 0;
});

onMounted(() => {
    loadRecords();
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable
                v-model:selection="selectedProviderUserRoles"
                :value="pagination.data"
                dataKey="id"
                :loading="loading"
                responsiveLayout="scroll"
                stripedRows
                size="small"
            >
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.provider_user_roles.table.title") }}
                        </h3>
                        <div class="flex gap-4">
                            <Button
                                v-if="hasSelectedProviderUserRoles && !tableComponent.showRecordsDeleted"
                                variant="text"
                                severity="danger"
                                @click="deleteSelectedProviderUserRoles"
                                v-tooltip.top="$t('admin.provider_user_roles.table.delete_selected_tooltip')"
                                ><Icon icon="material-symbols:delete-outline-rounded" width="24" height="24" />
                            </Button>
                            <Button
                                v-if="hasSelectedProviderUserRoles && tableComponent.showRecordsDeleted"
                                variant="text"
                                severity="warn"
                                @click="restoreSelectedProviderUserRoles"
                                v-tooltip.top="$t('admin.provider_user_roles.table.restore_selected_tooltip')"
                                ><Icon
                                    icon="material-symbols:restore-from-trash-outline-rounded"
                                    width="24"
                                    height="24"
                                    class="text-orange-500"
                                />
                            </Button>
                            <Button
                                variant="text"
                                @click="toggleShowRecordsDeleted"
                                v-tooltip.top="
                                    tableComponent.showRecordsDeleted
                                        ? $t('admin.provider_user_roles.table.hide_deleted_tooltip')
                                        : $t('admin.provider_user_roles.table.show_deleted_tooltip')
                                "
                            >
                                <Icon
                                    icon="material-symbols:delete-forever-outline-rounded"
                                    width="24"
                                    height="24"
                                    :class="tableComponent.showRecordsDeleted ? 'text-red-500' : 'text-gray-500'"
                                />
                            </Button>
                            <IconField iconPosition="left">
                                <InputIcon class="pi pi-search text-surface-400" />
                                <InputText
                                    v-model="filter"
                                    :placeholder="$t('admin.provider_user_roles.table.search_placeholder')"
                                    @input="onFilterChange"
                                    class="rounded-lg!"
                                />
                            </IconField>
                        </div>
                    </div>
                </template>

                <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

                <Column :header="$t('admin.provider_user_roles.table.user')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.user" class="font-bold text-surface-900">
                            {{ slotProps.data.user.username }}
                        </span>
                        <span v-else class="text-surface-400 italic">{{
                            $t("admin.provider_user_roles.table.missing_user")
                        }}</span>
                    </template>
                </Column>

                <Column :header="$t('admin.provider_user_roles.table.provider')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.provider" class="text-surface-700 font-medium">
                            {{ slotProps.data.provider.name }}
                        </span>
                        <span v-else class="text-surface-400 italic">{{
                            $t("admin.provider_user_roles.table.missing_provider")
                        }}</span>
                    </template>
                </Column>

                <Column :header="$t('admin.provider_user_roles.table.role')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.role" class="text-surface-600">
                            {{ slotProps.data.role.name }}
                        </span>
                        <span v-else class="text-surface-400 italic">{{
                            $t("admin.provider_user_roles.table.missing_role")
                        }}</span>
                    </template>
                </Column>
                <Column
                    field="deleted_at"
                    :header="$t('admin.provider_user_roles.table.deleted_at')"
                    v-if="tableComponent.showRecordsDeleted === true"
                >
                    <template #body="slotProps">
                        <span class="text-surface-600">{{ formatDate(slotProps.data.deleted_at) }}</span>
                    </template>
                </Column>

                <Column :header="$t('common.actions')" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button text rounded class="mr-1 hover:!bg-orange-50" @click="editItem(slotProps.data)"
                            ><Icon
                                icon="material-symbols:edit-outline"
                                width="24"
                                height="24"
                                class="text-yellow-400"
                            />
                        </Button>
                        <template v-if="slotProps.data.deleted_at">
                            <Button
                                text
                                rounded
                                severity="warn"
                                class="hover:!bg-red-50"
                                @click="confirmRestore(slotProps.data)"
                                ><Icon
                                    icon="material-symbols:restore-from-trash-outline-rounded"
                                    width="24"
                                    height="24"
                                    class="text-orange-500"
                                />
                            </Button>
                        </template>
                        <template v-else>
                            <Button
                                text
                                rounded
                                severity="danger"
                                class="hover:!bg-red-50"
                                @click="confirmDelete(slotProps.data)"
                                ><Icon icon="material-symbols:delete-outline-rounded" width="24" height="24" />
                            </Button>
                        </template>
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-8 text-surface-500">
                        <i class="pi pi-link text-4xl mb-4 text-surface-300"></i>
                        <p class="m-0">{{ $t("admin.provider_user_roles.table.empty") }}</p>
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
            :header="
                itemSelected
                    ? $t('admin.provider_user_roles.form.title_edit')
                    : $t('admin.provider_user_roles.form.title_create')
            "
            :style="{ width: '60vw', maxWidth: '800px' }"
            modal
            :draggable="false"
        >
            <ProviderUserRoleForm :itemSelected="itemSelected" @item-saved="onItemSaved" />
        </Dialog>

        <Dialog
            v-model:visible="displayRestoreModal"
            :header="$t('admin.provider_user_roles.restore.title')"
            :style="{ width: '450px' }"
            modal
            :draggable="false"
        >
            <div class="flex items-center gap-4 pt-2">
                <i class="pi pi-exclamation-triangle text-red-500 text-4xl"></i>
                <span v-if="itemSelected" class="text-surface-700">
                    {{ $t("admin.provider_user_roles.restore.prompt") }}
                    <b class="text-surface-900">{{ itemSelected.user ? itemSelected.user.username : "Selezionato" }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayRestoreModal = false" />
                <Button
                    :label="$t('common.restore')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="restoreProviderUserRoles([itemSelected.id])"
                    autofocus
                />
            </template>
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
                <span v-if="itemSelected" class="text-surface-700">
                    {{ $t("admin.provider_user_roles.delete.prompt") }}
                    <b class="text-surface-900">{{ itemSelected.user ? itemSelected.user.username : "Selezionato" }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button
                    :label="$t('common.delete')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteProviderUserRoles([itemSelected.id])"
                    autofocus
                />
            </template>
        </Dialog>
    </div>
</template>
