<script setup>
import { ref, watch, computed, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

// Componenti PrimeVue
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Button from "primevue/button";
import Message from "primevue/message";

// Props & Emits
const props = defineProps({
    selectedRole: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

// Stato Form
const loading = ref(false);
const loadingProviders = ref(false);
const providers = ref([]);

const form = ref({
    id: null,
    name: "",
    provider_id: null,
});

const errors = ref({
    name: "",
    provider_id: "",
});

// Computed
const isEditMode = computed(() => !!props.selectedRole);

// Metodi di utilità
const resetForm = () => {
    form.value = {
        name: "",
        provider_id: null,
    };
    resetErrors();
};

const resetErrors = () => {
    Object.keys(errors.value).forEach((key) => (errors.value[key] = ""));
};

// Logica di validazione lato client
const validate = () => {
    resetErrors();
    let isValid = true;

    if (!form.value.name) {
        errors.value.name = trans("admin.roles.form.validate.name.mandatory");
        isValid = false;
    }
    if (!form.value.provider_id) {
        errors.value.provider_id = trans("admin.roles.form.validate.provider.mandatory");
        isValid = false;
    }

    return isValid;
};

// Carica la lista dei provider dal backend
const loadProvidersList = async () => {
    loadingProviders.value = true;
    try {
        const res = await window.axios.get("/admin/v1/providers", {
            params: { per_page: 1000 },
        });
        providers.value = res.data.data || res.data;
    } catch (err) {
        console.error("Errore caricamento provider", err);
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.roles.toast.load_providers_error"),
            life: 3000,
        });
        emit("item-error", err);
    } finally {
        loadingProviders.value = false;
    }
};

// Submit Form
const submit = async () => {
    if (!validate()) return;

    loading.value = true;

    const baseUrl = "/admin/v1/roles";
    const url = isEditMode.value ? `${baseUrl}/${form.value.id}` : baseUrl;
    const method = isEditMode.value ? "put" : "post";

    const payload = {
        name: form.value.name,
        provider_id: form.value.provider_id,
    };

    try {
        await window.axios[method](url, payload);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: isEditMode.value
                ? trans("admin.roles.toast.detail_updated")
                : trans("admin.roles.toast.detail_created"),
            life: 3000,
        });
        emit("item-saved");
        resetForm();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.roles.toast.submit_error"),
            life: 3000,
        });
        emit("item-error", error);

        if (error.response?.data?.errors) {
            // Mappa gli errori di validazione del backend
            const backendErrors = error.response.data.errors;
            Object.keys(backendErrors).forEach((key) => {
                if (errors.value[key] !== undefined) {
                    errors.value[key] = backendErrors[key][0];
                }
            });
        }
    } finally {
        loading.value = false;
    }
};

// Carica il ruolo da modificare
const fetchRole = async (id) => {
    loading.value = true;
    try {
        const res = await window.axios.get(`/admin/v1/roles/${id}`);
        const data = res.data;
        form.value = {
            id: data.id,
            name: data.name,
            provider_id: data.provider_id,
        };
    } catch (err) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.roles.toast.load_role_error"),
            life: 3000,
        });
        emit("item-error", err);
    } finally {
        loading.value = false;
    }
};

// Watcher per riempire il form quando il padre ci passa un ruolo da modificare
watch(
    () => props.selectedRole,
    (newVal) => {
        if (newVal && newVal.id) {
            fetchRole(newVal.id);
            resetErrors();
        } else {
            resetForm();
        }
    },
    { immediate: true }
);

// Lifecycle
onMounted(() => {
    loadProvidersList();
});
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-6 w-full pt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
                <label for="name" class="font-medium text-surface-900">
                    {{ $t("admin.roles.form.name_label") }}
                </label>
                <InputText
                    id="name"
                    v-model="form.name"
                    :invalid="!!errors.name"
                    :placeholder="$t('admin.roles.form.name_placeholder')"
                    fluid
                />
                <Message v-if="errors.name" severity="error" size="small" variant="simple">
                    {{ errors.name }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="provider" class="font-medium text-surface-900">
                    {{ $t("admin.roles.form.provider_label") }}
                </label>
                <Select
                    id="provider"
                    v-model="form.provider_id"
                    :options="providers"
                    optionLabel="name"
                    optionValue="id"
                    :placeholder="$t('admin.roles.form.provider_placeholder')"
                    :invalid="!!errors.provider_id"
                    :loading="loadingProviders"
                    fluid
                    filter
                />
                <Message v-if="errors.provider_id" severity="error" size="small" variant="simple">
                    {{ errors.provider_id }}
                </Message>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-4 border-t border-surface-200 pt-4">
            <Button
                type="button"
                :label="$t('common.reset')"
                severity="secondary"
                text
                icon="pi pi-refresh"
                @click="resetForm"
                :disabled="loading"
            />
            <Button
                type="submit"
                :label="isEditMode ? $t('common.save_changes') : $t('admin.roles.form.btn_create')"
                icon="pi pi-check"
                :loading="loading"
            />
        </div>
    </form>
</template>
