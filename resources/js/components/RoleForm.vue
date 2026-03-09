<script setup>
import { ref, watch, computed, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

// Componenti PrimeVue
import InputText from "primevue/inputtext";
import Select from "primevue/select"; // Usiamo Select invece di Dropdown in PrimeVue 4
import Button from "primevue/button";
import Message from "primevue/message";

// Props & Emits
const props = defineProps({
    selectedRole: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["role-saved"]);
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
        id: null,
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
        errors.value.name = trans("admin.role_form.validate.name.mandatory");
        isValid = false;
    }
    if (!form.value.provider_id) {
        errors.value.provider_id = trans("admin.role_form.validate.provider.mandatory");
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
            summary: trans("admin.global.error"),
            detail: trans("admin.role_form.toast.error.load_providers"),
            life: 3000,
        });
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
            summary: trans("admin.global.success"),
            detail: isEditMode.value
                ? trans("admin.role_form.toast.success.updated")
                : trans("admin.role_form.toast.success.created"),
            life: 3000,
        });

        emit("role-saved");
        resetForm();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("admin.global.error"),
            detail: trans("admin.role_form.toast.error.submit"),
            life: 3000,
        });

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

// Watcher per riempire il form quando il padre ci passa un ruolo da modificare
watch(
    () => props.selectedRole,
    (newVal) => {
        if (newVal && newVal.id) {
            form.value.id = newVal.id;
            form.value.name = newVal.name;
            form.value.provider_id = newVal.provider_id;
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
                    {{ $t("admin.role_form.name_label") }}
                </label>
                <InputText
                    id="name"
                    v-model="form.name"
                    :invalid="!!errors.name"
                    :placeholder="$t('admin.role_form.name_placeholder')"
                    fluid
                />
                <Message v-if="errors.name" severity="error" size="small" variant="simple">
                    {{ errors.name }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="provider" class="font-medium text-surface-900">
                    {{ $t("admin.role_form.provider_label") }}
                </label>
                <Select
                    id="provider"
                    v-model="form.provider_id"
                    :options="providers"
                    optionLabel="domain"
                    optionValue="id"
                    :placeholder="$t('admin.role_form.provider_placeholder')"
                    :invalid="!!errors.provider_id"
                    :loading="loadingProviders"
                    fluid
                />
                <Message v-if="errors.provider_id" severity="error" size="small" variant="simple">
                    {{ errors.provider_id }}
                </Message>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-4 border-t border-surface-200 pt-4">
            <Button
                type="button"
                :label="$t('admin.global.reset')"
                severity="secondary"
                text
                icon="pi pi-refresh"
                @click="resetForm"
                :disabled="loading"
            />
            <Button
                type="submit"
                :label="isEditMode ? $t('admin.global.save_changes') : $t('admin.global.create')"
                icon="pi pi-check"
                :loading="loading"
            />
        </div>
    </form>
</template>
