<script setup>
import { ref, watch, computed } from "vue";
import { useToast } from "primevue/usetoast";

import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import Message from "primevue/message";

const props = defineProps({
    selectedProvider: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["provider-saved"]);
const toast = useToast();

const loading = ref(false);

const form = ref({
    id: null,
    domain: "",
    secretKey: "",
    logoutUrl: "",
    protocol: "",
});

const errors = ref({
    domain: "",
    secretKey: "",
});

const isEditMode = computed(() => !!props.selectedProvider);
const showSecretKeyTip = ref(false);

const resetForm = () => {
    form.value = {
        id: null,
        domain: "",
        secretKey: "",
        logoutUrl: "",
        protocol: "",
    };
    resetErrors();
};

const resetErrors = () => {
    errors.value.domain = "";
    errors.value.secretKey = "";
};

const validate = () => {
    resetErrors();
    let isValid = true;

    if (!form.value.domain) {
        errors.value.domain = "Il Dominio è obbligatorio";
        isValid = false;
    }

    if (!isEditMode.value && !form.value.secretKey) {
        errors.value.secretKey = "La Secret Key è obbligatoria per i nuovi provider";
        isValid = false;
    }

    return isValid;
};

const submit = async () => {
    if (!validate()) return;

    loading.value = true;

    const baseUrl = "/admin/v1/providers";
    const url = isEditMode.value ? `${baseUrl}/${form.value.id}` : baseUrl;
    const method = isEditMode.value ? "put" : "post";

    const payload = {
        domain: form.value.domain,
        logoutUrl: form.value.logoutUrl,
        protocol: form.value.protocol,
    };

    if (form.value.secretKey) {
        payload.secret_key = form.value.secretKey;
    }

    try {
        await window.axios[method](url, payload);
        toast.add({
            severity: "success",
            summary: "Operazione completata",
            detail: isEditMode.value ? "Provider aggiornato correttamente" : "Provider aggiunto correttamente",
            life: 3000,
        });
        emit("provider-saved");
        resetForm();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Errore",
            detail: "Errore durante il salvataggio del provider",
            life: 3000,
        });

        if (error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            if (backendErrors.domain) errors.value.domain = backendErrors.domain[0];
            if (backendErrors.secret_key) errors.value.secretKey = backendErrors.secret_key[0];
        }
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.selectedProvider,
    (newVal) => {
        if (newVal && newVal.id) {
            form.value.id = newVal.id;
            form.value.domain = newVal.domain || "";
            form.value.logoutUrl = newVal.logoutUrl || "";
            form.value.protocol = newVal.protocol || "";
            form.value.secretKey = ""; // Svuotiamo sempre per sicurezza
            resetErrors();
        } else {
            resetForm();
        }
    },
    { immediate: true }
);
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-6 w-full pt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
                <label for="domain" class="font-medium text-surface-900">Dominio</label>
                <InputText
                    id="domain"
                    v-model="form.domain"
                    :invalid="!!errors.domain"
                    placeholder="idp.newtimegroup.it"
                    fluid
                />
                <Message v-if="errors.domain" severity="error" size="small" variant="simple">{{
                    errors.domain
                }}</Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="logoutUrl" class="font-medium text-surface-900">Logout URL</label>
                <InputText
                    id="logoutUrl"
                    v-model="form.logoutUrl"
                    :placeholder="'https://' + (form.domain || 'dominio') + '/logout-idp'"
                    fluid
                />
                <small class="text-surface-500">Se vuoto, il valore è quello di default</small>
            </div>

            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2">
                    <label for="secretKey" class="font-medium text-surface-900">Secret Key</label>
                    <i
                        v-if="isEditMode"
                        class="pi pi-question-circle text-amber-500 hover:text-amber-600 transition-colors"
                        style="cursor: pointer; font-size: 0.875rem"
                        @click="showSecretKeyTip = !showSecretKeyTip"
                    ></i>
                </div>

                <Message v-if="isEditMode && showSecretKeyTip" severity="warn" size="small" variant="simple">
                    Lascia vuoto per non modificare la Secret Key attuale
                </Message>

                <Password
                    id="secretKey"
                    v-model="form.secretKey"
                    :invalid="!!errors.secretKey"
                    placeholder="Inserisci la Secret Key"
                    :feedback="false"
                    toggleMask
                    fluid
                />
                <Message v-if="errors.secretKey" severity="error" size="small" variant="simple">{{
                    errors.secretKey
                }}</Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="protocol" class="font-medium text-surface-900">Protocollo</label>
                <InputText id="protocol" v-model="form.protocol" fluid />
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-4 border-t border-surface-200 pt-4">
            <Button
                type="button"
                label="Reset"
                severity="secondary"
                text
                icon="pi pi-refresh"
                @click="resetForm"
                :disabled="loading"
            />
            <Button
                type="submit"
                :label="isEditMode ? 'Salva Modifiche' : 'Crea Provider'"
                icon="pi pi-check"
                :loading="loading"
            />
        </div>
    </form>
</template>
