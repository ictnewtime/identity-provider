<template>
    <Panel header="">
        <form @submit.prevent="submit" class="row justify-content-start">
            <div class="col-md-6 col-sm-12 field d-flex flex-column">
                <label for="username">Username</label>
                <InputText
                    id="username"
                    v-model="form.username"
                    :invalid="validator.username.length > 0"
                    placeholder="mario.rossi"
                    size="small"
                />
                <small class="p-error" v-if="validator.username.length">{{ validator.username[0] }}</small>
            </div>

            <div class="col-md-6 col-sm-12 field d-flex flex-column">
                <label for="email">Email</label>
                <InputText
                    id="email"
                    v-model="form.email"
                    :invalid="validator.email.length > 0"
                    placeholder="mario.rossi@example.com"
                    size="small"
                />
                <small class="p-error" v-if="validator.email.length">{{ validator.email[0] }}</small>
            </div>

            <div class="col-md-6 col-sm-12 field d-flex flex-column">
                <label for="name">Nome</label>
                <InputText
                    id="name"
                    v-model="form.name"
                    :invalid="validator.name.length > 0"
                    placeholder="Mario"
                    size="small"
                />
                <small class="p-error" v-if="validator.name.length">{{ validator.name[0] }}</small>
            </div>

            <div class="col-md-6 col-sm-12 field d-flex flex-column">
                <label for="surname">Cognome</label>
                <InputText
                    id="surname"
                    v-model="form.surname"
                    :invalid="validator.surname.length > 0"
                    placeholder="Rossi"
                    size="small"
                />
                <small class="p-error" v-if="validator.surname.length">{{ validator.surname[0] }}</small>
            </div>

            <div class="col-md-6 col-sm-12 field d-flex flex-column">
                <!-- margin left 3 -->
                <label for="password" class="ms-1">Password</label>
                <Password
                    id="password"
                    v-model="form.password"
                    :invalid="validator.password.length > 0"
                    :feedback="false"
                    toggleMask
                    size="small"
                    :pt="{
                        root: {
                            class: 'w-100',
                        },
                        pcInputText: {
                            root: {
                                class: 'w-100 p-inputtext-sm',
                            },
                        },
                    }"
                />
                <small class="p-error" v-if="validator.password.length">{{ validator.password[0] }}</small>
            </div>

            <div class="col-md-6 col-sm-12 field d-flex flex-column">
                <label for="password_confirmation">Conferma Password</label>
                <Password
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :invalid="validator.password_confirmation.length > 0"
                    :feedback="false"
                    toggleMask
                    size="small"
                    :pt="{
                        root: {
                            class: 'w-100',
                        },
                        pcInputText: {
                            root: {
                                class: 'w-100 p-inputtext-sm',
                            },
                        },
                    }"
                />
                <small class="p-error" v-if="validator.password_confirmation.length">{{
                    validator.password_confirmation[0]
                }}</small>
            </div>

            <div class="col-md-6 col-sm-12 field d-flex flex-column mt-2">
                <label for="enabled">Stato Utente</label>
                <div class="flex align-items-center gap-2 mt-1">
                    <ToggleSwitch id="enabled" v-model="form.enabled" />
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end align-items-center gap-3 mt-4">
                <Button
                    type="button"
                    label="Reset"
                    class="p-button-danger p-button-text"
                    icon="pi pi-times"
                    @click="resetForm"
                />

                <Button
                    type="submit"
                    :label="isEditMode ? 'Modifica Utente' : 'Crea Utente'"
                    :loading="loading"
                    icon="pi pi-check"
                />
            </div>
        </form>
    </Panel>
</template>

<script>
import Panel from "primevue/panel";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import ToggleSwitch from "primevue/toggleswitch";

export default {
    components: { Panel, InputText, Password, Button, ToggleSwitch },
    props: {
        // Riceve l'utente cliccato dalla tabella
        selectedUser: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            form: {
                id: null, // Aggiunto per l'Edit
                username: null,
                password: null,
                password_confirmation: null,
                email: null,
                name: null,
                surname: null,
                enabled: true,
            },
            validator: {
                username: [],
                email: [],
                password: [],
                password_confirmation: [],
                name: [],
                surname: [],
            },
            loading: false,
        };
    },

    computed: {
        // Capisce se siamo in modalità modifica guardando la prop
        isEditMode() {
            return !!this.selectedUser;
        },
    },

    watch: {
        // Reagisce ogni volta che la modale si apre con un utente diverso
        selectedUser: {
            immediate: true,
            handler(newVal) {
                if (newVal && newVal.id) {
                    this.fetchUser(newVal.id);
                } else {
                    this.resetForm();
                    this.loadUser(); // il tuo vecchio metodo per il localStorage
                }
            },
        },
    },

    mounted() {
        // this.loadUser(); // Spostato nel watcher per gestirlo in base allo stato
    },

    methods: {
        loadUser() {
            let user = JSON.parse(localStorage.getItem("user"));
            if (user && !this.isEditMode) {
                this.form.username = user.username;
                this.form.email = user.email;
                this.form.name = user.name;
                this.form.surname = user.surname;
                this.enabled = user.enabled;
            }
        },

        // Nuova chiamata GET per recuperare i dettagli
        fetchUser(id) {
            this.loading = true;
            axios
                .get(`${window.location.origin}/admin/v1/users/${id}`)
                .then((res) => {
                    const data = res.data;
                    this.form.id = data.id;
                    this.form.username = data.username;
                    this.form.email = data.email;
                    this.form.name = data.name;
                    this.form.surname = data.surname;
                    this.form.enabled = data.enabled == 1 ? true : false;
                    // Le password non vengono mai restituite dal backend, le lasciamo vuote
                    this.form.password = null;
                    this.form.password_confirmation = null;
                })
                .catch((err) => {
                    console.error(err);
                    vm.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore caricamento utente",
                        life: 3000,
                    });
                })
                .finally(() => (this.loading = false));
        },

        submit() {
            if (!this.validate()) return;

            this.loading = true;
            let vm = this;

            const baseUrl = window.location.origin + "/admin/v1/users";
            const url = this.isEditMode ? `${baseUrl}/${this.form.id}` : baseUrl;
            const method = this.isEditMode ? "put" : "post";

            // 1. Payload base (sempre inviato)
            let payload = {
                username: vm.form.username,
                email: vm.form.email,
                name: vm.form.name,
                surname: vm.form.surname,
                enabled: vm.form.enabled, // Assicurati di usare form.enabled
            };

            // 2. Aggiungi password solo se presente
            // In modifica: se l'utente non scrive nulla, la password non viene toccata
            if (vm.form.password && vm.form.password.trim() !== "") {
                payload.password = vm.form.password;
                payload.password_confirmation = vm.form.password_confirmation;
            }

            axios[method](url, payload)
                .then(function () {
                    vm.resetForm();
                    // toast
                    vm.$toast.add({
                        severity: "success",
                        summary: "Operazione completata",
                        detail: vm.isEditMode ? "Utente aggiornato correttamente" : "Utente aggiunto correttamente",
                        life: 3000,
                    });

                    // Notifica il componente padre (UserPage) per chiudere la modale e ricaricare la tabella
                    vm.$emit(vm.isEditMode ? "user-updated" : "user-created");
                })
                .catch(function (error) {
                    console.log(error);
                    vm.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: vm.isEditMode ? "Errore aggiornamento utente" : "Errore aggiunta utente",
                        life: 3000,
                    });
                    if (error.response && error.response.data.errors) {
                        vm.validator = { ...vm.validator, ...error.response.data.errors };
                    }
                })
                .finally(() => (vm.loading = false));
        },

        validate() {
            // Svuota prima i vecchi errori
            Object.keys(this.validator).forEach((key) => (this.validator[key] = []));

            let isValid = true;

            // Validazioni standard
            if (!this.form.username) {
                this.validator.username = ["Username obbligatorio"];
                isValid = false;
            }
            if (!this.validateEmail(this.form.email)) {
                this.validator.email = ["Email non valida"];
                isValid = false;
            }
            if (!this.form.name) {
                this.validator.name = ["Nome obbligatorio"];
                isValid = false;
            }
            if (!this.form.surname) {
                this.validator.surname = ["Cognome obbligatorio"];
                isValid = false;
            }

            // VALIDAZIONE PASSWORD LOGICA:
            // Se NON siamo in modifica (Creazione), la password è OBBLIGATORIA.
            // Se siamo in modifica, la controlliamo SOLO se l'utente ha iniziato a scriverla.
            if (!this.isEditMode && !this.form.password) {
                this.validator.password = ["Password obbligatoria per nuovi utenti"];
                isValid = false;
            }

            // Se c'è qualcosa nel campo password, verifichiamo la conferma (sia in Edit che Create)
            if (this.form.password && this.form.password !== this.form.password_confirmation) {
                this.validator.password_confirmation = ["Le password non coincidono"];
                isValid = false;
            }

            return isValid;
        },

        validateEmail(email) {
            let re =
                /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        },

        resetForm() {
            this.form.id = null;
            this.form.username = null;
            this.form.password = null;
            this.form.password_confirmation = null;
            this.form.email = null;
            this.form.name = null;
            this.form.surname = null;
            this.enabled = true;

            // Pulisce anche gli errori del validatore
            Object.keys(this.validator).forEach((key) => (this.validator[key] = []));
        },
    },
};
</script>
