Laravel Breeze con Inertia.js e Vue

🚀 Piano d'Azione per il POC (5 Giorni)
Giorno 1: Il Motore (IDP Backend)
Installazione: Nuovo progetto Laravel.

Passport: Installazione e configurazione di Laravel Passport (flusso Authorization Code).

Database: Setup delle tabelle base (users, oauth_clients).

Output: Un IDP capace di emettere token via Postman/cURL.

Giorno 2: L'Interfaccia (IDP Frontend in Vue)
Il trucco per velocizzare: Usa Laravel Breeze con Inertia.js e Vue. Ti darà un sistema di login già pronto in Vue.

Customizzazione: Modifica la pagina di Login e crea la pagina di "Consenso" (la pagina dove l'utente clicca "Autorizzo l'app XYZ ad accedere ai miei dati").

Giorno 3: L'Applicazione Client (Il Consumatore)
Crea una seconda applicazione (può essere un altro mini-progetto Laravel o solo Vue).

Il trucco per velocizzare: Usa Laravel Socialite. Anche se si usa spesso per Facebook/Google, è facilissimo creare un provider custom in Socialite che punti al tuo IDP.

Aggiungi il pulsante "Accedi con il mio IDP".

Giorno 4: L'Handshake (Il momento della verità)
È il giorno dell'integrazione. Configura il Client nell'IDP (client_id, client_secret, redirect_uri).

Fai funzionare il flusso: Click sul Client → Redirect all'IDP (Vue) → Login → Redirect al Client con il codice → Scambio del codice per il Token.

Giorno 5: Il Profilo Utente e Debug (POC Completo)
Usa il token ottenuto dal Client per chiamare l'endpoint api/user dell'IDP.

Mostra il nome e l'email dell'utente nel Client.

Risolvi i (probabili) problemi di CORS e sessioni incrociate.
