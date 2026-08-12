# Informativa sulla Privacy

**Ultimo aggiornamento:** 10 agosto 2026  
**Titolare del trattamento:** BeachOrder (privacy@beachorder.example)

## 1. Chi siamo

BeachOrder è una piattaforma SaaS che consente ai lidi e beach bar di gestire ordini tramite codice QR. Per i dati degli ospiti che ordinano dal proprio ombrellone, il **Titolare del trattamento** è il singolo stabilimento balneare (tenant); BeachOrder agisce come **Responsabile del trattamento** per conto del cliente business.

## 2. Dati che raccogliamo

### Utenti staff (operatori del lido)
- Nome, indirizzo email, password (crittografata)
- Ruolo e tenant di appartenenza
- Data accettazione Termini e Condizioni

### Ospiti (clienti finali via QR)
- Identificativo di sessione anonimo (UUID) — non richiede registrazione
- Nome opzionale e note ordine (solo se forniti volontariamente)
- Preferenza lingua (localStorage)

### Dati tecnici
- Indirizzo IP (troncato nei log di audit)
- Token di autenticazione staff (Sanctum)
- Log operativi (azioni di sicurezza, con PII mascherata)

**Non raccogliamo** dati non necessari: nessun tracciamento pubblicitario, nessun profilo comportamentale.

## 3. Finalità e base giuridica

| Finalità | Base giuridica |
|----------|----------------|
| Erogazione del servizio SaaS | Esecuzione contratto (Art. 6(1)(b) GDPR) |
| Gestione ordini QR | Legittimo interesse del titolare (lido) / contratto |
| Sicurezza e audit | Legittimo interesse (Art. 6(1)(f) GDPR) |
| Adempimenti legali | Obbligo di legge (Art. 6(1)(c) GDPR) |

## 4. Conservazione dei dati

| Categoria | Periodo |
|-----------|---------|
| Ordini completati | 365 giorni (poi anonimizzazione) |
| Chiamate cameriere | 90 giorni |
| Token accesso QR scaduti | 7 giorni |
| Log di audit | 730 giorni |
| Account staff | Fino a cancellazione account |

I periodi sono configurabili tramite variabili d'ambiente (`PRIVACY_RETENTION_*`).

## 5. I tuoi diritti

Ai sensi del GDPR (Art. 15–22) hai diritto a:

- **Accesso** e **portabilità** dei tuoi dati (export da area staff)
- **Rettifica** dei dati inesatti
- **Cancellazione** ("diritto all'oblio") — disponibile per account staff e sessioni ospiti
- **Limitazione** e **opposizione** al trattamento
- **Reclamo** all'Autorità Garante (www.garanteprivacy.it)

Per esercitare i diritti: privacy@beachorder.example

## 6. Cookie e storage locale

BeachOrder **non utilizza cookie di tracciamento**. Usa localStorage/sessionStorage per funzionalità essenziali (sessione ordine, carrello, lingua, consenso). Vedi la [Cookie Policy](/cookies).

## 7. Sub-responsabili

L'infrastruttura può includere provider di hosting, database e servizi email. L'elenco aggiornato è disponibile su richiesta e nel DPA per clienti business.

## 8. Trasferimenti extra-UE

I dati sono trattati preferibilmente nell'UE/SEE. Eventuali trasferimenti avvengono con garanzie adeguate (SCC, decisioni di adeguatezza).

## 9. Modifiche

Eventuali modifiche saranno pubblicate su questa pagina con data di aggiornamento.
