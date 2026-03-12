<script setup>
import { ref, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n"; // Importato per usare le traduzioni nello script

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Paginator from "primevue/paginator";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

import ProviderForm from "./ProviderForm.vue";

const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const selectedProvider = ref(null);
const displayDeleteModal = ref(false);
const providerToDelete = ref(null);
let searchTimeout = null;

const loadProviders = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/providers", {
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
                detail: trans("admin.providers.toast.load_error"),
                life: 3000,
            });
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadProviders(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadProviders(1);
    }, 500);
};

const openCreateModal = () => {
    selectedProvider.value = null;
    displayModal.value = true;
};

defineExpose({
    openCreateModal,
});

const onProviderSaved = () => {
    displayModal.value = false;
    loadProviders();
};

const editProvider = (provider) => {
    selectedProvider.value = provider;
    displayModal.value = true;
};

const confirmDelete = (provider) => {
    providerToDelete.value = provider;
    displayDeleteModal.value = true;
};

const deleteProvider = () => {
    if (!providerToDelete.value) return;

    window.axios
        .delete(`/admin/v1/providers/${providerToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            providerToDelete.value = null;
            loadProviders();
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.providers.toast.delete_success"),
                life: 3000,
            });
        })
        .catch((error) => {
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.providers.toast.delete_error"),
                life: 3000,
            });
        });
};

onMounted(() => {
    loadProviders();
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.providers.table.title") }}
                        </h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search text-surface-400" />
                            <InputText
                                v-model="filter"
                                :placeholder="$t('admin.providers.table.search_placeholder')"
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

                <Column field="domain" :header="$t('admin.providers.table.domain')">
                    <template #body="slotProps">
                        <span class="font-bold text-surface-900">{{ slotProps.data.domain }}</span>
                    </template>
                </Column>

                <Column field="name" :header="$t('admin.providers.table.name')">
                    <template #body="slotProps">
                        <span class="font-medium text-surface-700">{{ slotProps.data.name }}</span>
                    </template>
                </Column>

                <Column field="logoutUrl" :header="$t('admin.providers.table.logout_url')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.logoutUrl" class="text-surface-600">
                            {{ slotProps.data.logoutUrl }}
                        </span>
                        <span v-else class="text-surface-400 italic">
                            {{ $t("admin.providers.table.default_url") }}
                        </span>
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
                            @click="editProvider(slotProps.data)"
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
                        <i class="pi pi-server text-4xl mb-4 text-surface-300"></i>
                        <p class="m-0">
                            {{ $t("admin.providers.table.empty") }}
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
            :header="selectedProvider ? $t('admin.providers.form.title_edit') : $t('admin.providers.form.title_create')"
            :style="{ width: '60vw', maxWidth: '800px' }"
            modal
            :draggable="false"
        >
            <ProviderForm :selectedProvider="selectedProvider" @provider-saved="onProviderSaved" />
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
                <span v-if="providerToDelete" class="text-surface-700">
                    {{ $t("admin.providers.delete.prompt") }}
                    <b class="text-surface-900">{{ providerToDelete.domain }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button
                    :label="$t('common.delete')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteProvider"
                    autofocus
                />
            </template>
        </Dialog>
    </div>
</template>
