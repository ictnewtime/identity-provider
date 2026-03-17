<script setup>
import { ref, watch, computed, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n"; // Import per le traduzioni nello script

import Select from "primevue/select";
import Button from "primevue/button";
import Message from "primevue/message";

const props = defineProps({
    selectedItem: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

const loadingSubmit = ref(false);
const loadingData = ref(false);
const loadingRoles = ref(false);

const users = ref([]);
const providers = ref([]);
const roles = ref([]);

const form = ref({
    id: null,
    user_id: null,
    provider_id: null,
    role_id: null,
});

const errors = ref({
    user_id: "",
    provider_id: "",
    role_id: "",
});

const isEditMode = computed(() => !!props.selectedItem);

// Caricamento inziale Utenti e Provider
const loadInitialData = async () => {
    loadingData.value = true;
    try {
        const [usersRes, providersRes] = await Promise.all([
            window.axios.get("/admin/v1/users", { params: { per_page: 1000 } }),
            window.axios.get("/admin/v1/providers", { params: { per_page: 100 } }),
        ]);
        users.value = usersRes.data.data || usersRes.data;
        providers.value = providersRes.data.data || providersRes.data;
    } catch (err) {
        console.error(err);
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.provider_user_roles.toast.load_error"),
            life: 3000,
        });
        emit("item-error", err);
    } finally {
        loadingData.value = false;
    }
};

// Funzione per scaricare i ruoli filtrati per provider
const fetchRoles = async (providerId) => {
    loadingRoles.value = true;
    try {
        const res = await window.axios.get("/admin/v1/roles", {
            params: { per_page: 1000, provider_id: providerId },
        });
        roles.value = res.data.data || res.data;
    } catch (err) {
        console.error(err);
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.provider_user_roles.toast.roles_error"),
            life: 3000,
        });
        emit("item-error", err);
    } finally {
        loadingRoles.value = false;
    }
};

const onProviderChange = () => {
    form.value.role_id = null;
    roles.value = [];

    if (form.value.provider_id) {
        fetchRoles(form.value.provider_id);
    }
};

const resetForm = () => {
    form.value = {
        user_id: null,
        provider_id: null,
        role_id: null,
    };
    roles.value = [];
    resetErrors();
};

const resetErrors = () => {
    errors.value = {
        user_id: "",
        provider_id: "",
        role_id: "",
    };
};

const validate = () => {
    resetErrors();
    let isValid = true;

    if (!form.value.user_id) {
        errors.value.user_id = trans("admin.provider_user_roles.form.validate.user.mandatory");
        isValid = false;
    }
    if (!form.value.provider_id) {
        errors.value.provider_id = trans("admin.provider_user_roles.form.validate.provider.mandatory");
        isValid = false;
    }
    if (!form.value.role_id) {
        errors.value.role_id = trans("admin.provider_user_roles.form.validate.role.mandatory");
        isValid = false;
    }

    return isValid;
};

const submit = async () => {
    if (!validate()) return;

    loadingSubmit.value = true;

    const baseUrl = "/admin/v1/provider-user-roles";
    const url = isEditMode.value ? `${baseUrl}/${form.value.id}` : baseUrl;
    const method = isEditMode.value ? "put" : "post";

    const payload = {
        user_id: form.value.user_id,
        provider_id: form.value.provider_id,
        role_id: form.value.role_id,
    };

    try {
        await window.axios[method](url, payload);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: isEditMode.value
                ? trans("admin.provider_user_roles.toast.detail_updated")
                : trans("admin.provider_user_roles.toast.detail_created"),
            life: 3000,
        });
        emit("item-saved");
        resetForm();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.provider_user_roles.toast.submit_error"),
            life: 3000,
        });
        emit("item-error", err);

        if (error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            if (backendErrors.user_id) errors.value.user_id = backendErrors.user_id[0];
            if (backendErrors.provider_id) errors.value.provider_id = backendErrors.provider_id[0];
            if (backendErrors.role_id) errors.value.role_id = backendErrors.role_id[0];
        }
    } finally {
        loadingSubmit.value = false;
    }
};

watch(
    () => props.selectedItem,
    async (newVal) => {
        if (newVal && newVal.id) {
            form.value.id = newVal.id;
            form.value.user_id = newVal.user_id;
            form.value.provider_id = newVal.provider_id;

            roles.value = [];
            resetErrors();

            if (newVal.provider_id) {
                await fetchRoles(newVal.provider_id);
                form.value.role_id = newVal.role_id;
            }
        } else {
            resetForm();
        }
    },
    { immediate: true }
);

onMounted(() => {
    loadInitialData();
});
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-6 w-full pt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1 md:col-span-2">
                <label for="user_id" class="font-medium text-surface-900">
                    {{ $t("admin.provider_user_roles.form.user_label") }}
                </label>
                <Select
                    id="user_id"
                    v-model="form.user_id"
                    :options="users"
                    optionLabel="username"
                    optionValue="id"
                    :placeholder="$t('admin.provider_user_roles.form.user_placeholder')"
                    :invalid="!!errors.user_id"
                    :loading="loadingData"
                    filter
                    fluid
                />
                <Message v-if="errors.user_id" severity="error" size="small" variant="simple">
                    {{ errors.user_id }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="provider_id" class="font-medium text-surface-900">
                    {{ $t("admin.provider_user_roles.form.provider_label") }}
                </label>
                <Select
                    id="provider_id"
                    v-model="form.provider_id"
                    :options="providers"
                    optionLabel="name"
                    optionValue="id"
                    :placeholder="$t('admin.provider_user_roles.form.provider_placeholder')"
                    :invalid="!!errors.provider_id"
                    :loading="loadingData"
                    @change="onProviderChange"
                    filter
                    fluid
                />
                <Message v-if="errors.provider_id" severity="error" size="small" variant="simple">
                    {{ errors.provider_id }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="role_id" class="font-medium text-surface-900">
                    {{ $t("admin.provider_user_roles.form.role_label") }}
                </label>
                <Select
                    id="role_id"
                    v-model="form.role_id"
                    :options="roles"
                    optionLabel="name"
                    optionValue="id"
                    :placeholder="$t('admin.provider_user_roles.form.role_placeholder')"
                    :invalid="!!errors.role_id"
                    :loading="loadingRoles"
                    :disabled="!form.provider_id || loadingRoles"
                    fluid
                />
                <Message v-if="errors.role_id" severity="error" size="small" variant="simple">
                    {{ errors.role_id }}
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
                :disabled="loadingSubmit"
            />
            <Button
                type="submit"
                :label="isEditMode ? $t('common.save_changes') : $t('admin.provider_user_roles.form.btn_create')"
                icon="pi pi-check"
                :loading="loadingSubmit"
            />
        </div>
    </form>
</template>
