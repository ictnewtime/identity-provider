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

            <div class="col-12 mt-3">
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

export default {
    components: { Panel, InputText, Password, Button },
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
                    // Le password non vengono mai restituite dal backend, le lasciamo vuote
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

            // Logica dinamica: URL e Metodo HTTP variano
            const baseUrl = window.location.origin + "/admin/v1/users";
            const url = this.isEditMode ? `${baseUrl}/${this.form.id}` : baseUrl;
            const method = this.isEditMode ? "put" : "post";

            // Costruiamo il payload
            let payload = {
                username: vm.form.username,
                email: vm.form.email,
                name: vm.form.name,
                surname: vm.form.surname,
            };

            // Invieremo la password solo se l'utente l'ha digitata (per aggiornarla)
            // o se stiamo creando un utente nuovo
            if (vm.form.password) {
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

            this.validator.username = !!this.form.username ? [] : ["Username obbligatorio"];
            this.validator.email = this.validateEmail(this.form.email) ? [] : ["Email non valida"];
            this.validator.name = !!this.form.name ? [] : ["Nome obbligatorio"];
            this.validator.surname = !!this.form.surname ? [] : ["Cognome obbligatorio"];

            // La password è obbligatoria solo in creazione. In modifica è opzionale.
            if (!this.isEditMode && !this.form.password) {
                this.validator.password = ["Password obbligatoria"];
            }

            // ... Salva nel localStorage se ti serve ancora

            return (
                this.validator.username.length === 0 &&
                this.validator.password.length === 0 &&
                this.validator.email.length === 0 &&
                this.validator.name.length === 0 &&
                this.validator.surname.length === 0
            );
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

            // Pulisce anche gli errori del validatore
            Object.keys(this.validator).forEach((key) => (this.validator[key] = []));
        },
    },
};
</script>
