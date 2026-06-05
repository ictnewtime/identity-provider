# Documentazione Core: Identity Provider (IdP)

## 1. Architettura di Base

L'Identity Provider (IdP) funge da sistema centralizzato di Single Sign-On (SSO). Gestisce l'autenticazione degli utenti e rilascia permessi specifici (Ruoli) per i vari applicativi client (Provider) collegati, utilizzando token JWT firmati con chiavi segrete univoche per ogni provider.

Il sistema è diviso in due macro-aree:

- **Frontend (SPA)**: Sviluppato in Vue 3 con PrimeVue, buildato tramite Vite.
- **Backend (API)**: Sviluppato in Laravel 11+, gestisce il database, l'autenticazione globale e l'emissione dei token.

---

## 2. Flusso di Autenticazione e SSO (Single Sign-On)

Il cuore del sistema garantisce che un utente debba inserire le credenziali una sola volta (Grant-Token globale) per accedere in modo trasparente a tutti i Provider a cui è abilitato.

### A. Primo Accesso (Login)

1. L'utente inserisce le credenziali nel Frontend dell'IdP.
2. L'`AuthController@login` valida le credenziali tramite `Auth::attempt`.
3. Se la richiesta proviene da un Provider specifico (`provider_id` presente):

- Viene invocato il `SessionService` per generare un token JWT e registrare la sessione nel DB.
- Viene generato un Cookie HTTP-Only contenente il token.
- L'utente viene reindirizzato all'URL del Provider (con gestione speciale per gli ambienti `localhost`, dove il token viene accodato in query string).

### B. Accesso Successivo (Middleware `RedirectIfAuthenticated`)

Se un utente ha già una sessione attiva sull'IdP (Grant-Token) e tenta di accedere a un altro Provider:

1. Il middleware intercetta la richiesta.
2. Controlla nella tabella `sessions` se esiste già un token valido per la coppia `User-Provider`.
3. **Controllo di Sicurezza (IP Binding)**: Se la sessione esiste ma l'indirizzo IP dell'utente è cambiato, la vecchia sessione viene distrutta e ne viene generata una nuova. Questo previene il furto di token (Session Hijacking).
4. Se la sessione è valida e l'IP coincide, il token esistente viene riutilizzato, risparmiando risorse crittografiche.

---

## 3. Servizi Chiave (Backend)

- **`TokenProviderService`**:
- È la "fabbrica" dei JWT. Legge i ruoli dell'utente (`ProviderUserRoleService`), applica la `secret_key` specifica del Provider richiedente e firma il token.
- Detiene la "Fonte di Verità" sul TTL (Time To Live) del token, garantendo che JWT e Sessione DB scadano allo stesso identico millisecondo.
- Gestisce la formattazione sicura dei Cookie (HTTP-Only, Secure, SameSite) e i workaround per gli ambienti locali (`appendTokenIfLocalUrl`).

- **`SessionService`**:
- Orchestra il salvataggio fisico dei token nel Database (tabella `sessions`).
- Contiene la logica `getValidProviderToken` per decidere se riciclare un token esistente o forzarne la rigenerazione.

---

## 4. Gestione Frontend (Pannello Admin)

Il frontend amministrativo è stato modernizzato passando da Laravel Mix a Vite per un HMR (Hot Module Replacement) istantaneo in Docker.
La UI è standardizzata su 4 entità principali: **Utenti, Provider, Ruoli, Associazioni (ProviderUserRole)**.

- **Componenti Unificati**: Le pagine (es. `UserPage`) includono sia la `<DataTable>` paginata lato server, sia le modali per Creazione/Modifica e per l'Eliminazione sicura.
- **UX Avanzata**:
- Uso estensivo del `ToastService` nativo di PrimeVue per i feedback visivi (abbandonando l'EventBus).
- **Dropdown a cascata**: Nella gestione delle associazioni, la selezione di un Provider filtra in automatico (via API) solo i ruoli appartenenti a quel Provider, impedendo configurazioni errate.

---

---

## 💡 Prossimi Step e TODO (Punti aperti da smarcare)

### 1. Implementazione del `refresh_token`

Nella migrazione abbiamo aggiunto la colonna `refresh_token` a database, ma attualmente il `SessionService` passa sempre `null` in fase di creazione.

- **Cosa fare**: Generare una stringa sicura (es. `Str::random(60)`) nel `TokenProviderService`, salvarla nel DB e creare un endpoint API (`/api/refresh`) che i Provider possono chiamare per rinnovare il JWT scaduto senza costringere l'utente a passare per un redirect visivo all'IdP.
