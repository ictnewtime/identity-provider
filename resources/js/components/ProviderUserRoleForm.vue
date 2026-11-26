<script setup>
import { ref, watch, computed, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import Select from "primevue/select";
import Button from "primevue/button";
import Message from "primevue/message";
import { fetchAllPages } from "@/utils/pagination";

const props = defineProps({
    itemSelected: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["item-success", "item-error"]);
const toast = useToast();

const loadingSubmit = ref(false);
const loadingData = ref(false);
const loadingRoles = ref(false);

// Utenti: si cercano sul server mentre si scrive nel filtro. Caricarli tutti all'apertura non
// funziona: il backend restituisce al massimo 100 righe per pagina, e chi viene dopo non compariva mai.
const USER_SUGGESTIONS = 20;
const USER_SEARCH_DELAY_MS = 300;
const userSelect = ref(null);
const userOptions = ref([]);
const loadingUsers = ref(false);
// L'utente scelto resta tra le opzioni anche quando la ricerca non lo restituisce: senza, il
// Select non sa che nome mostrare (succede in modifica, e dopo una nuova ricerca).
const currentUser = ref(null);
let userSearchTimeout = null;
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

const isEditMode = computed(() => !!props.itemSelected);

const withCurrentUser = (users) => {
    const current = currentUser.value;
    return current && !users.some((user) => user.id === current.id) ? [current, ...users] : users;
};

const searchUsers = async (text) => {
    loadingUsers.value = true;
    try {
        const res = await window.axios.get("/admin/v1/users", {
            params: { q: text?.trim() || undefined, per_page: USER_SUGGESTIONS, sort_by: "username" },
        });
        userOptions.value = withCurrentUser(res.data.data || []);
    } catch (err) {
        console.error(err);
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.provider_user_roles.toast.load_error"),
            life: 3000,
        });
    } finally {
        loadingUsers.value = false;
    }
};

// Si osserva il testo del filtro del Select e non il suo evento `filter`: quando si scrive col menu
// chiuso, il Select si apre e mette la PRIMA lettera nel filtro senza emettere l'evento.
watch(
    () => userSelect.value?.filterValue,
    (text) => {
        clearTimeout(userSearchTimeout);
        userSearchTimeout = setTimeout(() => searchUsers(text), USER_SEARCH_DELAY_MS);
    },
);

const onUserChange = () => {
    currentUser.value = userOptions.value.find((user) => user.id === form.value.user_id) ?? null;
};

// Caricamento iniziale dei Provider
const loadInitialData = async () => {
    loadingData.value = true;
    try {
        const [allProviders] = await Promise.all([fetchAllPages("/admin/v1/providers"), searchUsers("")]);
        providers.value = allProviders;
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
        roles.value = await fetchAllPages("/admin/v1/roles", { provider_id: providerId });
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
    currentUser.value = null;
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

const BACKEND_ERROR_FIELDS = ["user_id", "provider_id", "role_id"];

const applyBackendErrors = (error) => {
    const backendErrors = error.response?.data?.errors;

    if (!backendErrors) {
        return;
    }

    BACKEND_ERROR_FIELDS.forEach((field) => {
        if (backendErrors[field]) {
            errors.value[field] = backendErrors[field][0];
        }
    });
};

const savedDetail = () =>
    isEditMode.value
        ? trans("admin.provider_user_roles.toast.detail_updated")
        : trans("admin.provider_user_roles.toast.detail_created");

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
            detail: savedDetail(),
            life: 3000,
        });
        emit("item-success");
        resetForm();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.provider_user_roles.toast.submit_error"),
            life: 3000,
        });
        emit("item-error", error);
        applyBackendErrors(error);
    } finally {
        loadingSubmit.value = false;
    }
};

watch(
    () => props.itemSelected,
    async (newVal) => {
        if (newVal && newVal.id) {
            form.value.id = newVal.id;
            form.value.user_id = newVal.user_id;
            currentUser.value = newVal.user ?? { id: newVal.user_id, username: String(newVal.user_id) };
            userOptions.value = withCurrentUser(userOptions.value);
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
                <label id="user_id-label" for="user_id" class="font-medium text-surface-900">
                    {{ $t("admin.provider_user_roles.form.user_label") }}
                </label>
                <!--
                    autoFilterFocus: scrivendo col menu chiuso, il cursore passa nel filtro e le
                    lettere si compongono. Senza, ogni lettera sostituiva la precedente.
                    filterFields: il filtro locale guarda gli stessi campi della ricerca sul server
                    (username ed email), cosi' non scarta i risultati trovati per email.
                -->
                <Select
                    ref="userSelect"
                    id="user_id"
                    aria-labelledby="user_id-label"
                    v-model="form.user_id"
                    :options="userOptions"
                    optionLabel="username"
                    optionValue="id"
                    :filterFields="['username', 'email']"
                    :placeholder="$t('admin.provider_user_roles.form.user_placeholder')"
                    :invalid="!!errors.user_id"
                    :loading="loadingUsers"
                    @change="onUserChange"
                    filter
                    autoFilterFocus
                    resetFilterOnHide
                    fluid
                />
                <Message v-if="errors.user_id" severity="error" size="small" variant="simple">
                    {{ errors.user_id }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label id="provider_id-label" for="provider_id" class="font-medium text-surface-900">
                    {{ $t("admin.provider_user_roles.form.provider_label") }}
                </label>
                <Select
                    id="provider_id"
                    aria-labelledby="provider_id-label"
                    v-model="form.provider_id"
                    :options="providers"
                    optionLabel="name"
                    optionValue="id"
                    :filterFields="['name', 'domain']"
                    :placeholder="$t('admin.provider_user_roles.form.provider_placeholder')"
                    :invalid="!!errors.provider_id"
                    :loading="loadingData"
                    @change="onProviderChange"
                    filter
                    autoFilterFocus
                    fluid
                />
                <Message v-if="errors.provider_id" severity="error" size="small" variant="simple">
                    {{ errors.provider_id }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label id="role_id-label" for="role_id" class="font-medium text-surface-900">
                    {{ $t("admin.provider_user_roles.form.role_label") }}
                </label>
                <Select
                    id="role_id"
                    aria-labelledby="role_id-label"
                    v-model="form.role_id"
                    :options="roles"
                    optionLabel="name"
                    optionValue="id"
                    :placeholder="$t('admin.provider_user_roles.form.role_placeholder')"
                    :invalid="!!errors.role_id"
                    :loading="loadingRoles"
                    :disabled="!form.provider_id || loadingRoles"
                    filter
                    autoFilterFocus
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
