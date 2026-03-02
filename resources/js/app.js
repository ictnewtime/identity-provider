import "./bootstrap";
import { createApp } from "vue";

import PrimeVue from "primevue/config";
import "primeicons/primeicons.css";
import Aura from "@primeuix/themes/aura";
import ToastService from "primevue/toastservice";

// 1. Importiamo tutti i componenti (Ora con estensione .vue obbligatoria)
// import UsersComponent from "./components/UsersComponent.vue";
import UserTable from "./components/UserTable.vue";
import ProviderTable from "./components/ProviderTable.vue";
import LoginForm from "./components/LoginForm.vue";
import RoleFormComponent from "./components/RoleTable.vue";
import Paginator from "./components/Paginator.bkp.vue";
import CompleteRegistrationForm from "./components/CompleteRegistrationForm.vue";
import OauthClientsTable from "./components/OauthClientsTable.vue";
import NewClientFormComponent from "./components/NewClientFormComponent.vue";
// import NotificationComponent from "./components/NotificationComponent.old.vue";
import Notification from "./components/Notification.vue";
import ProviderUserRoleTable from "./components/ProviderUserRoleTable.vue";
import SessionTable from "./components/SessionTable.vue";

// 2. Creiamo l'istanza dell'applicazione Vue 3
const app = createApp({});
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            cssLayer: false,
            darkModeSelector: "light",
            prefix: "p",
        },
    },
});
app.use(ToastService);

// 3. Registriamo i componenti usando i tag che usi nel tuo HTML
app.component("users", UserTable);
app.component("notification", Notification);
app.component("providers", ProviderTable);
app.component("login-form", LoginForm);
app.component("roles", RoleFormComponent);
app.component("provider-user-roles", ProviderUserRoleTable);
app.component("paginator", Paginator);
app.component("complete-registration-form", CompleteRegistrationForm);
app.component("oauth-clients", OauthClientsTable);
app.component("newclient-form", NewClientFormComponent);
app.component("sessions", SessionTable);

// 4. Montiamo l'app sul div con id="app"
app.mount("#app");
