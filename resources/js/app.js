import "./bootstrap";
import { createApp } from "vue";

import PrimeVue from "primevue/config";
import Aura from "@primeuix/themes/aura";
import ToastService from "primevue/toastservice";

// 1. Importiamo tutti i componenti (Ora con estensione .vue obbligatoria)
// import UsersComponent from "./components/UsersComponent.vue";
import UsersPage from "./components/UserPage.vue";
import NotificationComponent from "./components/NotificationComponent.old.vue";
import ProviderFormComponent from "./components/ProviderFormComponent.vue";
import LoginForm from "./components/LoginForm.vue";
import RoleFormComponent from "./components/RoleFormComponent.vue";
import Paginator from "./components/Paginator.vue";
import CompleteRegistrationForm from "./components/CompleteRegistrationForm.vue";
import OauthClientsTable from "./components/OauthClientsTable.vue";
import NewClientFormComponent from "./components/NewClientFormComponent.vue";

// 2. Creiamo l'istanza dell'applicazione Vue 3
const app = createApp({});
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            prefix: "p",
            darkModeSelector: "light",
            cssLayer: false,
        },
    },
});
app.use(ToastService);

// 3. Registriamo i componenti usando i tag che usi nel tuo HTML
app.component("users", UsersPage);
app.component("notification", NotificationComponent);
app.component("provider-form", ProviderFormComponent);
app.component("login-form", LoginForm);
app.component("manage-roles-panel", RoleFormComponent);
app.component("paginator", Paginator);
app.component("complete-registration-form", CompleteRegistrationForm);
app.component("oauth-clients", OauthClientsTable);
app.component("newclient-form", NewClientFormComponent);

// 4. Montiamo l'app sul div con id="app"
app.mount("#app");
