# Ruoli Controller / Processor

**Ultimo aggiornamento:** 10 agosto 2026

## Panoramica

BeachOrder opera come piattaforma multi-tenant. I ruoli GDPR variano in base al tipo di dato e al soggetto interessato.

## Matrice dei ruoli

| Scenario | Titolare (Controller) | Responsabile (Processor) |
|----------|----------------------|--------------------------|
| Dati staff del beach bar | Beach bar (tenant) | BeachOrder |
| Dati ospiti che ordinano via QR | Beach bar (tenant) | BeachOrder |
| Dati account admin BeachOrder (super_admin) | BeachOrder | — |
| Dati registrazione tenant (ragione sociale) | Beach bar | BeachOrder (per onboarding) |
| Log di audit piattaforma | BeachOrder | Sub-responsabili hosting |

## Responsabilità del Titolare (beach bar)

Il cliente business (beach bar) come Titolare deve:

1. **Informare gli ospiti** — fornire un'informativa privacy del proprio stabilimento (BeachOrder fornisce il modello)
2. **Base giuridica** — definire la base giuridica per il trattamento dati ospiti (tipicamente legittimo interesse o contratto)
3. **Diritti interessati** — gestire richieste degli ospiti; BeachOrder fornisce strumenti tecnici (anonimizzazione sessione)
4. **DPA** — firmare l'Accordo sul Trattamento dei Dati con BeachOrder

## Responsabilità di BeachOrder (Processor)

BeachOrder come Responsabile deve:

1. Trattare i dati solo su istruzioni del Titolare
2. Implementare misure di sicurezza (crittografia password, tenant isolation, rate limiting)
3. Minimizzare i dati raccolti (sessioni anonime, PII opzionale)
4. Applicare policy di retention automatica
5. Redigere PII nei log di audit
6. Fornire export e cancellazione per account staff
7. Mettere a disposizione il registro attività di trattamento

## Flusso dati

```
Ospite → QR → BeachOrder API → DB tenant-scoped
                                    ↓
                              Beach bar (accesso admin)
                                    ↓
                              Export / cancellazione su richiesta
```

## Contatti

- **Privacy (Controller):** privacy@beachorder.example
- **DPO / Processor:** dpo@beachorder.example

## Riferimenti

- [Informativa Privacy](/legal/privacy)
- [DPA](/legal/dpa)
- [Registro dati (API)](/admin/settings/privacy)
