import './bootstrap';
import { createApp } from 'vue';

// 1. Importiamo tutti i componenti (Ora con estensione .vue obbligatoria)
import UsersComponent from './components/UsersComponent.vue';
import NotificationComponent from './components/NotificationComponent.vue';
import ProviderFormComponent from './components/ProviderFormComponent.vue';
import LoginForm from './components/LoginForm.vue';
import RoleFormComponent from './components/RoleFormComponent.vue';
import Paginator from './components/Paginator.vue';
import CompleteRegistrationForm from './components/CompleteRegistrationForm.vue';
import OauthClientsTable from './components/OauthClientsTable.vue';
import NewClientFormComponent from './components/NewClientFormComponent.vue';

// 2. Creiamo l'istanza dell'applicazione Vue 3
const app = createApp({});

// 3. Registriamo i componenti usando i tag che usi nel tuo HTML
app.component('users', UsersComponent);
app.component('notification', NotificationComponent);
app.component('provider-form', ProviderFormComponent);
app.component('login-form', LoginForm);
app.component('manage-roles-panel', RoleFormComponent);
app.component('paginator', Paginator);
app.component('complete-registration-form', CompleteRegistrationForm);
app.component('oauth-clients', OauthClientsTable);
app.component('newclient-form', NewClientFormComponent);

// 4. Montiamo l'app sul div con id="app"
app.mount('#app');