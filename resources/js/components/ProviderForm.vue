<template>
    <form @submit.prevent="submit" class="row justify-content-start">
        <div class="col-lg-6 field d-flex flex-column mb-3">
            <label for="input-domain">Dominio</label>
            <InputText
                id="input-domain"
                v-model="form.domain"
                :invalid="validator.domain.length > 0"
                placeholder="idp.newtimegroup.it"
            />
            <small class="p-error" v-if="validator.domain.length">{{ validator.domain[0] }}</small>
        </div>

        <div class="col-lg-6 field d-flex flex-column mb-3">
            <label for="input-logout-url">Logout URL</label>
            <InputText
                id="input-logout-url"
                v-model="form.logoutUrl"
                :placeholder="'https://' + (form.domain || 'dominio') + '/logout-idp'"
            />
            <small class="text-muted">Se vuoto, il valore è quello di default</small>
        </div>

        <div class="col-lg-6 field d-flex flex-column mb-4">
            <label for="input-secret-key">Secret Key</label>
            <Password
                id="input-secret-key"
                v-model="form.secretKey"
                :invalid="validator.secretKey.length > 0"
                placeholder="Inserisci la Secret Key"
                toggleMask
                :feedback="false"
                :pt="{
                    root: { class: 'w-100' },
                    pcInputText: { root: { class: 'w-100' } },
                }"
            />
            <small class="p-error" v-if="validator.secretKey.length">{{ validator.secretKey[0] }}</small>
            <small class="text-muted" v-if="isEditMode">Lascia vuoto per non modificare la Secret Key attuale</small>
        </div>

        <div class="col-lg-6 field d-flex flex-column mb-3">
            <label for="input-protocol">Protocollo</label>
            <InputText id="input-protocol" v-model="form.protocol" />
        </div>

        <div class="col-12 mt-2 d-flex justify-content-end">
            <Button
                type="submit"
                :label="isEditMode ? 'Salva Modifiche' : 'Crea Provider'"
                :loading="loading"
                icon="pi pi-check"
            />
        </div>
    </form>
</template>

<script>
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";

export default {
    name: "ProviderForm",
    components: { InputText, Password, Button },
    props: {
        selectedProvider: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            form: {
                id: null,
                domain: null,
                secretKey: null,
                logoutUrl: null,
            },
            validator: {
                domain: [],
                secretKey: [],
                logoutUrl: [],
            },
            loading: false,
        };
    },
    computed: {
        isEditMode() {
            return !!this.selectedProvider;
        },
    },
    watch: {
        selectedProvider: {
            immediate: true,
            handler(newVal) {
                if (newVal && newVal.id) {
                    // Precompila i campi (tranne la secretKey per sicurezza)
                    this.form.id = newVal.id;
                    this.form.domain = newVal.domain;
                    this.form.logoutUrl = newVal.logoutUrl || null;
                    this.form.secretKey = null;
                    this.form.protocol = newVal.protocol || null;
                    this.clearErrors();
                } else {
                    this.resetForm();
                }
            },
        },
    },
    methods: {
        submit() {
            if (!this.validate()) return;

            this.loading = true;
            let vm = this;

            const baseUrl = window.location.origin + "/admin/v1/providers";
            const url = this.isEditMode ? `${baseUrl}/${this.form.id}` : baseUrl;
            const method = this.isEditMode ? "put" : "post";

            let payload = {
                domain: vm.form.domain,
                logoutUrl: vm.form.logoutUrl,
                protocol: vm.form.protocol,
            };

            if (vm.form.secretKey) {
                payload.secret_key = vm.form.secretKey;
            }

            axios[method](url, payload)
                .then(() => {
                    vm.$toast.add({
                        severity: "success",
                        summary: "Operazione completata",
                        detail: vm.isEditMode ? "Provider aggiornato correttamente" : "Provider aggiunto correttamente",
                        life: 3000,
                    });
                    vm.$emit("provider-saved");
                    vm.resetForm();
                })
                .catch((error) => {
                    vm.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore durante il salvataggio del provider.",
                        life: 3000,
                    });

                    if (error.response && error.response.data.errors) {
                        vm.validator = { ...vm.validator, ...error.response.data.errors };
                    }
                })
                .finally(() => (vm.loading = false));
        },

        validate() {
            this.clearErrors();

            this.validator.domain = !!this.form.domain ? [] : ["Dominio obbligatorio"];

            // La Secret Key è obbligatoria in Creazione, ma opzionale in Modifica
            if (!this.isEditMode && !this.form.secretKey) {
                this.validator.secretKey = ["Secret Key obbligatoria"];
            }

            return this.validator.domain.length === 0 && this.validator.secretKey.length === 0;
        },

        clearErrors() {
            this.validator.domain = [];
            this.validator.secretKey = [];
            this.validator.logoutUrl = [];
            this.validator.protocol = [];
        },

        resetForm() {
            this.form.id = null;
            this.form.domain = null;
            this.form.secretKey = null;
            this.form.logoutUrl = null;
            this.form.protocol = null;
            this.clearErrors();
        },
    },
};
</script>
