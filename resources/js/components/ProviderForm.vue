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
    url: "",
    domain: "",
    secret_key: "",
    logoutUrl: "",
    protocol: "",
});

const errors = ref({
    domain: "",
    url: "",
    secret_key: "",
    logoutUrl: "",
    protocol: "",
});

const isEditMode = computed(() => !!props.selectedProvider);
const showsecret_keyTip = ref(false);

const resetForm = () => {
    form.value = {
        id: null,
        url: "",
        domain: "",
        secret_key: "",
        logoutUrl: "",
        protocol: "",
    };
    resetErrors();
};

const resetErrors = () => {
    errors.value.domain = "";
    errors.value.url = "";
    errors.value.secret_key = "";
    errors.value.logoutUrl = "";
    errors.value.protocol = "";
};

const validate = () => {
    resetErrors();
    let isValid = true;

    if (!form.value.domain) {
        errors.value.domain = "Il Dominio è obbligatorio";
        isValid = false;
    }

    if (!isEditMode.value && !form.value.secret_key) {
        errors.value.secret_key = "La Secret Key è obbligatoria per i nuovi provider";
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

    if (form.value.secret_key) {
        payload.secret_key = form.value.secret_key;
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
            if (backendErrors.secret_key) errors.value.secret_key = backendErrors.secret_key[0];

            // AGGIUNGI QUESTE DUE RIGHE:
            if (backendErrors.logoutUrl) errors.value.logoutUrl = backendErrors.logoutUrl[0];
            if (backendErrors.protocol) errors.value.protocol = backendErrors.protocol[0];
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
            form.value.url = newVal.url || "";
            form.value.domain = newVal.domain || "";
            form.value.logoutUrl = newVal.logoutUrl || "";
            form.value.protocol = newVal.protocol || "";
            form.value.secret_key = ""; // Svuotiamo sempre per sicurezza
            resetErrors();
        } else {
            resetForm();
        }
    },
    { immediate: true }
);

// 1. Funzione per estrarre i dati dall'URL
const parseUrlAndFill = () => {
    if (!form.value.url) return;

    let rawUrl = form.value.url.trim();

    // Se l'utente digita "localhost:8000" senza http, l'API URL di JS va in errore. Lo proteggiamo:
    if (!rawUrl.startsWith("http://") && !rawUrl.startsWith("https://")) {
        rawUrl = "http://" + rawUrl;
    }

    try {
        const parsed = new URL(rawUrl);

        // Assegna i valori estratti
        // parsed.protocol restituisce "http:" o "https:". Rimuoviamo i due punti.
        form.value.protocol = parsed.protocol.replace(":", "");

        // parsed.host prende dominio e porta (es. localhost:8000), parsed.hostname solo il dominio.
        // Di solito per l'IdP è meglio 'host' se usi porte diverse in dev.
        form.value.domain = parsed.hostname;

        // Costruiamo la rotta di logout di default
        form.value.logoutUrl = `${parsed.protocol}//${parsed.host}/logout`;
    } catch (error) {
        // Se l'URL è scritto malissimo
        toast.add({ severity: "warn", summary: "Attenzione", detail: "Formato URL non valido", life: 3000 });
    }
};

// 2. Funzione per generare una Secret Key di 32 caratteri (alfa-num + speciali)
const generateSecret = () => {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+~|}{[]:;?><,./-=";
    let secret = "";

    // Generazione sicura basata su array crittografico del browser (meglio del semplice Math.random)
    const randomValues = new Uint32Array(32);
    window.crypto.getRandomValues(randomValues);

    for (let i = 0; i < 32; i++) {
        secret += chars[randomValues[i] % chars.length];
    }

    form.value.secret_key = secret;
};
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-6 w-full pt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1 md:col-span-2">
                <label for="url" class="font-medium text-surface-900">URL Principale App</label>
                <InputGroup class="flex gap-2">
                    <InputText
                        id="url"
                        v-model="form.url"
                        class="flex-grow"
                        placeholder="es. https://myapp.com o localhost:8000"
                    />
                    <Button
                        icon="pi pi-sparkles"
                        severity="secondary"
                        @click="parseUrlAndFill"
                        v-tooltip.top="'Estrai Dominio, Protocollo e Logout URL'"
                    />
                </InputGroup>
                <small class="text-surface-500"
                    >Inserisci l'URL e usa la bacchetta per auto-compilare i campi sottostanti.</small
                >
            </div>

            <div class="flex flex-col gap-1">
                <label for="domain" class="font-medium text-surface-900">Dominio</label>
                <InputText
                    id="domain"
                    v-model="form.domain"
                    :invalid="!!errors.domain"
                    placeholder="idp.newtimegroup.it"
                    fluid
                />
                <Message v-if="errors.domain" severity="error" size="small" variant="simple">
                    {{ errors.domain }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="protocol" class="font-medium text-surface-900">Protocollo</label>
                <InputText id="protocol" v-model="form.protocol" placeholder="http o https" fluid />
            </div>

            <div class="flex flex-col gap-1 md:col-span-2">
                <label for="logoutUrl" class="font-medium text-surface-900">Logout URL</label>
                <InputText
                    id="logoutUrl"
                    v-model="form.logoutUrl"
                    :invalid="!!errors.logoutUrl"
                    :placeholder="(form.protocol || 'https') + '://' + (form.domain || 'dominio') + '/logout'"
                    fluid
                />
                <Message v-if="errors.logoutUrl" severity="error" size="small" variant="simple">
                    {{ errors.logoutUrl }}
                </Message>
                <small v-else class="text-surface-500">Se vuoto, il valore è quello di default</small>
            </div>

            <div class="flex flex-col gap-1 md:col-span-2">
                <div class="flex items-center gap-2">
                    <label for="secret_key" class="font-medium text-surface-900">Secret Key</label>
                    <i
                        v-if="isEditMode"
                        class="pi pi-question-circle text-amber-500 hover:text-amber-600 transition-colors"
                        style="cursor: pointer; font-size: 0.875rem"
                        @click="showsecret_keyTip = !showsecret_keyTip"
                    ></i>
                </div>

                <Message v-if="isEditMode && showsecret_keyTip" severity="warn" size="small" variant="simple">
                    Lascia vuoto per non modificare la Secret Key attuale
                </Message>

                <InputGroup class="flex gap-2">
                    <Password
                        id="secret_key"
                        v-model="form.secret_key"
                        :invalid="!!errors.secret_key"
                        placeholder="Inserisci o genera la Secret Key"
                        :feedback="false"
                        toggleMask
                        fluid
                        class="flex-grow"
                    />
                    <Button
                        icon="pi pi-refresh"
                        severity="secondary"
                        @click="generateSecret"
                        v-tooltip.top="'Genera chiave sicura casuale (32 caratteri)'"
                    />
                </InputGroup>

                <Message v-if="errors.secret_key" severity="error" size="small" variant="simple">
                    {{ errors.secret_key }}
                </Message>
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
