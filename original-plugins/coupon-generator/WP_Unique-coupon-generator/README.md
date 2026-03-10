# Unique Coupon Generator

Plugin WordPress avanzato per la generazione di coupon unici, la gestione di programmi fidelity e un flusso completo di ticketing/eventi con integrazione WooCommerce, invio digitale (PDF/PNG/WhatsApp) e strumenti CRM.

## Indice
- [Panoramica](#panoramica)
- [Funzionalità principali](#funzionalità-principali)
- [Requisiti](#requisiti)
- [Installazione rapida](#installazione-rapida)
- [Configurazione iniziale](#configurazione-iniziale)
- [Gestione coupon](#gestione-coupon)
- [Gestione eventi e ticket](#gestione-eventi-e-ticket)
- [Flusso di pagamento WooCommerce](#flusso-di-pagamento-woocommerce)
- [Marketing e CRM](#marketing-e-crm)
- [WhatsApp e download digitali](#whatsapp-e-download-digitali)
- [Shortcode disponibili](#shortcode-disponibili)
- [Processi pianificati](#processi-pianificati)
- [Log e strumenti di debug](#log-e-strumenti-di-debug)
- [Struttura del progetto](#struttura-del-progetto)
- [Sviluppo](#sviluppo)
- [Licenza e supporto](#licenza-e-supporto)

## Panoramica
Unique Coupon Generator centralizza in un unico plugin:

- la generazione di coupon personalizzati con campi dinamici e QR code;
- la gestione di eventi e ticket digitali (manuali o acquistati online);
- un programma fidelity con punteggi, log e terminale di verifica;
- strumenti marketing per anagrafica clienti, invio email e template;
- canali di consegna multipli (download diretto o invio via WhatsApp).

Tutte le interfacce amministrative sono raccolte sotto il menu **“Gestione Coupon”** nel back-office WordPress.

## Funzionalità principali
- **Set di coupon illimitati** con campi personalizzati, privacy, immagini dedicate, stato (bozza/attivo/chiuso) e shortcode generati automaticamente.
- **Generazione automatica di coupon unici** con QR code, invio email e tracciamento dell’avvenuta consegna per ciascun utente.
- **Programma fidelity integrato** con punti per euro speso, bonus di iscrizione, log dettagliati e strumenti di consultazione da back-office.
- **Ticketing eventi** completo: creazione eventi, tipologie di ticket, gestione PR, promemoria email, wizard front-end a step con pagamenti in loco o WooCommerce.
- **Integrazione WooCommerce** per vendere ticket online, riconciliare ordini, generare documenti e reindirizzare l’utente allo step finale del wizard.
- **Distribuzione digitale** dei ticket/coupon tramite download PNG/PDF o link WhatsApp con messaggio personalizzabile.
- **Check-in e pannello PR** con QR scanner HTML5, cambio stato ticket, verifica pagamenti, esport PDF.
- **Moduli marketing/CRM**: schede anagrafiche, segmentazione per età/città, filtri per coupon o eventi, invio campagne email e gestione template.
- **Log errori e strumenti diagnostici** con tabella dedicata nel database e pagina admin per la consultazione/clean-up.
- **Traduzioni e compatibilità Elementor**: il plugin rileva il contesto Elementor e carica solo gli shortcode necessari.

## Requisiti
- WordPress 5.0 o superiore.
- PHP 7.4 o superiore (raccomandato).
- WooCommerce (opzionale) per l’acquisto online dei ticket.
- Cron di WordPress attivo per l’invio dei promemoria eventi.

## Installazione rapida
1. Carica la cartella `unique-coupon-generator` in `wp-content/plugins` oppure installa lo ZIP dal pannello WordPress.
2. Attiva il plugin da **Plugin → Plugin installati**.
3. Alla prima attivazione verrai reindirizzato alla pagina di benvenuto per l’inserimento della licenza e la configurazione iniziale.

## Configurazione iniziale
- **Access Gate**: tramite `class-ucg-access-gate.php` il plugin verifica la licenza con la classe singleton `UCG_Access_Gate` prima di abilitare le funzioni core.
- **Menu principale**: tutte le schermate sono sotto **Gestione Coupon** con schede dedicate a coupon, eventi, marketing, WhatsApp e impostazioni generali.
- **Permessi**: l’accesso è riservato a utenti con capability `manage_options` (alcune sezioni, come i report PR, sono disponibili anche ai ruoli WooCommerce).

## Gestione coupon
### Set e generazione
- Crea set dalla scheda *Set e generazione* definendo codice base, campi richiesti (nome, cognome, email, telefono, campi custom), immagini e stato.
- Ogni set genera automaticamente la pagina pubblica e lo shortcode `[richiedi_coupon base="CODICE"]`.
- È possibile limitare le modalità di consegna (PNG/PDF/WhatsApp) e configurare privacy policy obbligatorie.

### Front-end e invio
- Il form `[richiedi_coupon]` crea/aggiorna l’utente WordPress, valida il numero di telefono con prefisso, gestisce il consenso privacy e obbliga alla scelta di un singolo canale di consegna.
- Genera un coupon univoco per utente/set, produce il QR code e invia l’email di conferma tramite `CouponGenerator::genera_coupon_unico_per_utente()` e `invia_email_coupon()`.
- I download immediati (PNG/PDF) vengono resi disponibili a fine processo; in alternativa l’utente riceve il link WhatsApp.

### Verifica e fidelizzazione
- `[verifica_coupon]` espone un form con scanner QR per controllare validità e stato dei coupon.
- La scheda *Fidelity* consente di impostare punti per euro e bonus di iscrizione, consultare i log e verificare rapidamente il saldo clienti.
- È disponibile l’elenco dei clienti fidelizzati con totale punti, importi spesi e utilizzi.

## Gestione eventi e ticket
### Creazione eventi
- Crea eventi dalla scheda *Gestione Eventi*: titolo, descrizione, date, luogo, ticket disponibili, blocchi vendite, PR associati, immagini e pagine generate automaticamente.
- Per ogni evento si configurano email di conferma/promemoria (con placeholder come `{customer_name}`, `{event_title}`, `{ticket_code}`) e canali di consegna (PNG, PDF, WhatsApp).
- Supporto a pagamenti multipli: in loco/manuale e WooCommerce con selezione delle gateway consentite.

### Wizard front-end
- `[richiedi_ticket base="ID_EVENTO"]` attiva il wizard a step: raccolta dati partecipante, scelta ticket, modalità di pagamento e riepilogo.
- In modalità manuale il ticket viene generato immediatamente con link di download e (se selezionato) link WhatsApp.
- In modalità WooCommerce il wizard salva i dati, crea l’ordine con metadati `_ucg_event_id` e reindirizza al checkout.

### Check-in, PR e report
- `[verifica_ticket]` permette il check-in da front-end con QR scanner, cambio stato e riepilogo evento/ticket.
- `[ticket_pr]` è l’area riservata ai PR (richiede login) per segnare pagamenti, vedere informazioni cliente e scaricare i documenti.
- Nel back-office sono disponibili schede per ticket generati, stato di validazione, report vendite e performance PR.

### Email e remind
- Il plugin invia automaticamente email di conferma con QR code e promemoria in base ai giorni configurati (`ucg_events_process_reminders`).
- I template supportano contenuti HTML e personalizzazione tramite placeholder sostituiti al volo.

## Flusso di pagamento WooCommerce
- Gli hook `woocommerce_checkout_create_order_line_item`, `woocommerce_payment_complete`, `woocommerce_order_status_processing` e `woocommerce_order_status_completed` gestiscono la creazione dei ticket, l’aggiornamento degli stati e la generazione dei documenti.
- Il redirect sul “thank you” page è implementato da `ucg_events_output_wc_redirect`, agganciato a `woocommerce_thankyou` **solo** per gli ordini che includono prodotti con meta `_ucg_event_id` (ticket). In assenza di articoli ticket il comportamento WooCommerce resta invariato.
- Dopo il pagamento l’utente viene riportato allo step finale del wizard tramite `_ucg_wizard_return_url` e `_ucg_wizard_token`, dove può scaricare il ticket o ottenere il link WhatsApp.

## Marketing e CRM
- La scheda *Marketing* aggrega: anagrafiche utenti (coupon + eventi), filtri per città, fascia d’età, sorgente e stato coupon.
- Consente di costruire liste dinamiche per campagne email, con conteggio coupon generati/utilizzati e punti fidelity.
- È disponibile l’invio email massivo con feedback sugli esiti e un archivio dei template HTML da riutilizzare.
- I dati dei ticket evento sono fusi con quelli dei clienti WordPress per avere una vista unica del comportamento degli utenti.

## WhatsApp e download digitali
- Il tab dedicato consente di personalizzare il messaggio predefinito utilizzato dai link WhatsApp (`ucg_get_whatsapp_message_template`).
- Ogni form front-end offre l’opzione “Voglio ricevere il QR anche su WhatsApp”: il plugin genera link click-to-chat (`wa.me`) con i placeholder sostituiti (nome, titolo evento, link PDF/PNG).
- I link vengono messi in coda tramite transients (`ucg_queue_whatsapp_link`) e consumati al redirect; non è richiesto alcun gateway esterno.
- Le stesse impostazioni controllano la disponibilità dei pulsanti download PNG/PDF nello step finale.

## Shortcode disponibili
| Shortcode | Descrizione |
|-----------|-------------|
| `[richiedi_coupon base="SET"]` | Form pubblica per richiedere un coupon del set indicato. |
| `[verifica_coupon]` | Modulo di verifica con input manuale e scanner QR. |
| `[richiedi_ticket base="ID"]` | Wizard ticketing per l’evento specificato. |
| `[verifica_ticket]` | Check-in pubblico per staff/addetti con QR scanner. |
| `[ticket_pr]` | Area riservata ai PR per verificare pagamenti e ticket. |

## Processi pianificati
- `ucg_events_schedule_reminders` registra un cron giornaliero (`ucg_events_daily_reminder`) per inviare promemoria ai partecipanti.
- Durante l’attivazione vengono create le tabelle personalizzate (eventi, ticket, PR, punti fidelity, error log) e programmato il redirect alla pagina di benvenuto.
- Alla disattivazione gli scheduler per i promemoria vengono puliti.

## Log e strumenti di debug
- Tutte le anomalie passano da `ucg_log_error()`, che scrive su tabella `wp_ucg_error_log` e nel file `ucg_error.log`.
- La pagina **Log Errori** sotto Gestione Coupon mostra gli ultimi eventi registrati e permette di svuotare la tabella.
- In caso di errori fatali un handler di shutdown aggiunge automaticamente la traccia al file di log.

## Struttura del progetto
- `unique-coupon-generator.php`: bootstrap principale, hook WordPress/WooCommerce, gestione Elementor e caricamento automatico delle classi.
- `includes/`: contiene classi, trait e funzioni condivise (coupon, fidelity, eventi, marketing, WhatsApp, helper).
- `includes/events/`: logica ticketing (admin, front-end, database, email, gateway in loco, helper).
- `assets/`: fogli di stile e script (wizard ticket, gestione telefono, scanner QR).
- `templates/`: viste HTML per esportazioni e frontend avanzati.
- `languages/`: file di localizzazione (`unique-coupon-generator` come text domain).
- `Documentation/`: spazio riservato ai manuali e alla documentazione estesa.

## Sviluppo
- Il progetto utilizza Composer solo per l’autoload PSR-4 delle classi interne (non sono richieste dipendenze esterne).
- Dopo aver clonato il repository esegui `composer install` per rigenerare `vendor/autoload.php` se necessario.
- Gli asset sono già compilati; eventuali modifiche richiedono la ricostruzione manuale dei file JS/CSS presenti in `assets/`.
- Segui gli standard WordPress per filtri (`apply_filters`), azioni (`do_action`) e funzioni di escaping/sanitizzazione.

## Licenza e supporto
- Il plugin è rilasciato con licenza GPL2; alcune funzionalità sono protette dalla verifica effettuata da `UCG_Access_Gate` (wrapper globali `ucg_access_gate()`/`ucg_access_granted()`).
- L’abilitazione delle feature premium è ora mediata dai guardiani `ucg_enforce_access_point()` e `ucg_block_when_forbidden()`: ogni entry-point (frontend, AJAX, cron, invio email) deve passarvi un contesto interno per ottenere logging e messaggi coerenti con il canale.
- Il file `ucg-compliance-manifest.txt` riepiloga le condizioni di utilizzo per codice PHP e asset multimediali.
- Per assistenza o personalizzazioni avanzate contatta Meteora Web (<https://meteoraweb.com>).
