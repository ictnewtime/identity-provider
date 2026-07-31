# JWKS → PEM

Utility per estrarre la **chiave pubblica RSA** dell'IDP dal suo endpoint JWKS
(`/.well-known/jwks.json`) e convertirla in formato **PEM**.

Serve per **verificare i master token** emessi dall'IDP: sono JWT firmati con
`RS256`, verificabili con la chiave pubblica (`public.pem`) senza conoscere la
chiave privata. Usala per debug/test manuali del flusso SSO (es. controllare
firma, `sub`, `exp` di un token ricevuto via cookie o `?token=`).

## Prerequisiti

Python 3 con il modulo `venv`:

```bash
sudo apt install python3 python3.12-venv
```

## Setup (una tantum)

Crea un virtualenv e installa la dipendenza dello script (`cryptography`):

```bash
python3 -m venv path/to/venv
source path/to/venv/bin/activate
pip install cryptography
```

> Lo script importa `cryptography`: se lanci con il Python di sistema senza
> averlo installato, fallisce con `ModuleNotFoundError`. Assicurati che il
> virtualenv sia **attivo** (prompt con `(venv)`) prima dei comandi seguenti.

## 1. Recupera il JWKS

Scarica il JWKS dall'IDP (esempio staging):

```bash
curl -s https://idp-staging.newtimegroup.it/.well-known/jwks.json -o jwks.json
```

## 2. Genera la chiave pubblica PEM

```bash
python jwks_to_pem.py jwks.json --kid idp-master-key -o public.pem
```

- `--kid idp-master-key` seleziona la chiave del master token (vedi
  `config/idp.php` → `jwt.master_key_id`). Se ometti `--kid`, lo script usa la
  prima chiave del JWKS.
- `-o public.pem` scrive su file; senza `-o` la PEM viene stampata a video.

Risultato: `public.pem` (formato `SubjectPublicKeyInfo`), es.:

```
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...
-----END PUBLIC KEY-----
```

## 3. Verifica un master token

Con `public.pem` puoi validare un token, ad esempio su [jwt.io](https://jwt.io)
(incolla token e chiave pubblica) oppure da CLI:

```bash
# richiede: pip install pyjwt
python -c "import jwt,sys; print(jwt.decode(sys.argv[1], open('public.pem').read(), algorithms=['RS256']))" "<MASTER_TOKEN>"
```

## Note

- `jwks.json`, `public.pem` e `path/` (il virtualenv) **non sono versionati**
  (vedi `.gitignore`): sono contenuti fetchati/generati/locali, non codice.
- La chiave pubblica **non è un segreto** — è pubblica per definizione (endpoint
  JWKS). La chiave **privata** vive solo sull'IDP in `storage/app/keys/`.
