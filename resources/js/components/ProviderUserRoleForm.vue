<template>
    <form @submit.prevent="submit" class="row justify-content-start">
        <div class="col-lg-12 field d-flex flex-column mb-3">
            <label for="input-user">Utente</label>
            <Dropdown
                id="input-user"
                v-model="form.user_id"
                :options="users"
                optionLabel="email"
                optionValue="id"
                placeholder="Seleziona un Utente"
                :invalid="validator.user_id.length > 0"
                :loading="loadingData"
                filter
                class="w-full"
            />
            <small class="p-error" v-if="validator.user_id.length">{{ validator.user_id[0] }}</small>
        </div>

        <div class="col-lg-6 field d-flex flex-column mb-3">
            <label for="input-provider">Provider</label>
            <Dropdown
                id="input-provider"
                v-model="form.provider_id"
                :options="providers"
                optionLabel="domain"
                optionValue="id"
                placeholder="Seleziona Provider"
                :invalid="validator.provider_id.length > 0"
                :loading="loadingData"
                @change="onProviderChange"
                class="w-full"
            />
            <small class="p-error" v-if="validator.provider_id.length">{{ validator.provider_id[0] }}</small>
        </div>

        <div class="col-lg-6 field d-flex flex-column mb-3">
            <label for="input-role">Ruolo</label>
            <Dropdown
                id="input-role"
                v-model="form.role_id"
                :options="roles"
                optionLabel="name"
                optionValue="id"
                placeholder="Seleziona Ruolo"
                :invalid="validator.role_id.length > 0"
                :loading="loadingRoles"
                :disabled="!form.provider_id || loadingRoles"
                class="w-full"
            />
            <small class="p-error" v-if="validator.role_id.length">{{ validator.role_id[0] }}</small>
        </div>

        <div class="col-12 mt-3 d-flex justify-content-end">
            <Button
                type="submit"
                :label="isEditMode ? 'Salva Modifiche' : 'Crea Associazione'"
                :loading="loadingSubmit"
                icon="pi pi-check"
            />
        </div>
    </form>
</template>

<script>
import Dropdown from "primevue/dropdown";
import Button from "primevue/button";
import axios from "axios"; // Assicurati che axios sia importato se non è globale

export default {
    name: "ProviderUserRoleForm",
    components: { Dropdown, Button },
    props: {
        selectedItem: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            form: {
                id: null,
                user_id: null,
                provider_id: null,
                role_id: null,
            },
            users: [],
            providers: [],
            roles: [], // Sarà vuoto all'inizio

            loadingData: false,
            loadingRoles: false, // Nuovo stato di caricamento specifico per i ruoli
            loadingSubmit: false,

            validator: {
                user_id: [],
                provider_id: [],
                role_id: [],
            },
        };
    },
    computed: {
        isEditMode() {
            return !!this.selectedItem;
        },
    },
    watch: {
        selectedItem: {
            immediate: true,
            handler(newVal) {
                if (newVal && newVal.id) {
                    this.form.id = newVal.id;
                    this.form.user_id = newVal.user_id;
                    this.form.provider_id = newVal.provider_id;

                    this.roles = []; // Pulisce i vecchi ruoli
                    this.clearErrors();

                    // Se siamo in modifica e c'è un provider, scarichiamo i suoi ruoli
                    // e SOLO DOPO assegniamo il ruolo selezionato.
                    if (newVal.provider_id) {
                        this.fetchRoles(newVal.provider_id).then(() => {
                            this.form.role_id = newVal.role_id;
                        });
                    }
                } else {
                    this.resetForm();
                }
            },
        },
    },
    mounted() {
        this.loadInitialData();
    },
    methods: {
        // Al caricamento iniziale scarichiamo SOLO Utenti e Provider
        loadInitialData() {
            this.loadingData = true;
            Promise.all([
                axios.get("/admin/v1/users", { params: { per_page: 1000 } }),
                axios.get("/admin/v1/providers", { params: { per_page: 1000 } }),
            ])
                .then(([usersRes, providersRes]) => {
                    this.users = usersRes.data.data || usersRes.data;
                    this.providers = providersRes.data.data || providersRes.data;
                })
                .catch((err) => {
                    console.error(err);
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore nel caricamento dei dati di base",
                        life: 3000,
                    });
                })
                .finally(() => (this.loadingData = false));
        },

        // Nuova funzione per scaricare i ruoli filtrati
        fetchRoles(providerId) {
            this.loadingRoles = true;
            return axios
                .get("/admin/v1/roles", {
                    params: {
                        per_page: 1000,
                        provider_id: providerId, // Il parametro che il tuo Controller ora legge!
                    },
                })
                .then((res) => {
                    this.roles = res.data.data || res.data;
                })
                .catch((err) => {
                    console.error(err);
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Impossibile caricare i ruoli",
                        life: 3000,
                    });
                })
                .finally(() => (this.loadingRoles = false));
        },

        // Evento scatenato dall'utente quando seleziona un provider dalla tendina
        onProviderChange() {
            this.form.role_id = null; // Azzera il ruolo precedentemente selezionato
            this.roles = []; // Svuota la tendina

            // Se l'utente ha selezionato un valore valido, facciamo la chiamata
            if (this.form.provider_id) {
                this.fetchRoles(this.form.provider_id);
            }
        },

        submit() {
            if (!this.validate()) return;

            this.loadingSubmit = true;

            const baseUrl = "/admin/v1/provider-user-roles";
            const url = this.isEditMode ? `${baseUrl}/${this.form.id}` : baseUrl;
            const method = this.isEditMode ? "put" : "post";

            let payload = {
                user_id: this.form.user_id,
                provider_id: this.form.provider_id,
                role_id: this.form.role_id,
            };

            axios[method](url, payload)
                .then(() => {
                    this.resetForm();
                    this.$toast.add({
                        severity: "success",
                        summary: "Successo",
                        detail: this.isEditMode ? "Associazione aggiornata" : "Associazione creata",
                        life: 3000,
                    });
                    this.$emit("item-saved");
                })
                .catch((error) => {
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Impossibile salvare i dati",
                        life: 3000,
                    });
                    if (error.response?.data?.errors) {
                        this.validator = { ...this.validator, ...error.response.data.errors };
                    }
                })
                .finally(() => (this.loadingSubmit = false));
        },

        validate() {
            this.clearErrors();
            this.validator.user_id = this.form.user_id ? [] : ["Utente obbligatorio"];
            this.validator.provider_id = this.form.provider_id ? [] : ["Provider obbligatorio"];
            this.validator.role_id = this.form.role_id ? [] : ["Ruolo obbligatorio"];

            return (
                this.validator.user_id.length === 0 &&
                this.validator.provider_id.length === 0 &&
                this.validator.role_id.length === 0
            );
        },

        clearErrors() {
            this.validator.user_id = [];
            this.validator.provider_id = [];
            this.validator.role_id = [];
        },

        resetForm() {
            this.form.id = null;
            this.form.user_id = null;
            this.form.provider_id = null;
            this.form.role_id = null;
            this.roles = []; // Resettiamo anche i ruoli scaricati
            this.clearErrors();
        },
    },
};
</script>
