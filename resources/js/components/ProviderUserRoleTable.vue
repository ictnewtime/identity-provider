<script setup>
import { ref, onMounted } from "vue";
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

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const selectedItem = ref(null);
const displayDeleteModal = ref(false);
const itemToDelete = ref(null);
let searchTimeout = null;

const loadItems = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/provider-user-roles", {
            params: { page: page, per_page: pagination.value.per_page, q: filter.value },
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
    loadItems(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadItems(1);
    }, 500);
};

const openCreateModal = () => {
    selectedItem.value = null;
    displayModal.value = true;
};

// Esponiamo la funzione al componente padre
defineExpose({
    openCreateModal,
});

const onItemSaved = () => {
    displayModal.value = false;
    loadItems();
};

const editItem = (item) => {
    selectedItem.value = item;
    displayModal.value = true;
};

const confirmDelete = (item) => {
    itemToDelete.value = item;
    displayDeleteModal.value = true;
};

const deleteItem = () => {
    if (!itemToDelete.value) return;

    window.axios
        .delete(`/admin/v1/provider-user-roles/${itemToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            itemToDelete.value = null;
            loadItems();
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

onMounted(() => {
    loadItems();
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.provider_user_roles.table.title") }}
                        </h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search text-surface-400" />
                            <InputText
                                v-model="filter"
                                :placeholder="$t('admin.provider_user_roles.table.search_placeholder')"
                                @input="onFilterChange"
                                class="!rounded-lg"
                            />
                        </IconField>
                    </div>
                </template>

                <Column field="id" :header="$t('common.id')" style="width: 5%">
                    <template #body="slotProps">
                        <span class="text-surface-500 text-sm">{{ slotProps.data.id }}</span>
                    </template>
                </Column>

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

                <Column :header="$t('common.actions')" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-pencil"
                            text
                            rounded
                            severity="warn"
                            class="mr-1 hover:!bg-orange-50"
                            @click="editItem(slotProps.data)"
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
                selectedItem
                    ? $t('admin.provider_user_roles.form.title_edit')
                    : $t('admin.provider_user_roles.form.title_create')
            "
            :style="{ width: '60vw', maxWidth: '800px' }"
            modal
            :draggable="false"
        >
            <ProviderUserRoleForm :selectedItem="selectedItem" @item-saved="onItemSaved" />
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
                <span v-if="itemToDelete" class="text-surface-700">
                    {{ $t("admin.provider_user_roles.delete.prompt") }}
                    <b class="text-surface-900">{{ itemToDelete.user ? itemToDelete.user.email : "Selezionato" }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button
                    :label="$t('common.delete')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteItem"
                    autofocus
                />
            </template>
        </Dialog>
    </div>
</template>
