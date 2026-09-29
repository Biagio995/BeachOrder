# Ruoli Controller / Processor

**Ultimo aggiornamento:** 10 agosto 2026

## Panoramica

Servio opera come piattaforma multi-tenant. I ruoli GDPR variano in base al tipo di dato e al soggetto interessato.

## Matrice dei ruoli

| Scenario | Titolare (Controller) | Responsabile (Processor) |
|----------|----------------------|--------------------------|
| Dati staff del locale | Locale (tenant) | Servio |
| Dati ospiti che ordinano via QR | Locale (tenant) | Servio |
| Dati account admin Servio (super_admin) | Servio | — |
| Dati registrazione tenant (ragione sociale) | Locale (tenant) | Servio (per onboarding) |
| Log di audit piattaforma | Servio | Sub-responsabili hosting |

## Responsabilità del Titolare (ristorante)

Il cliente business (ristorante) come Titolare deve:

1. **Informare gli ospiti** — fornire un'informativa privacy del proprio locale (Servio fornisce il modello)
2. **Base giuridica** — definire la base giuridica per il trattamento dati ospiti (tipicamente legittimo interesse o contratto)
3. **Diritti interessati** — gestire richieste degli ospiti; Servio fornisce strumenti tecnici (anonimizzazione sessione)
4. **DPA** — firmare l'Accordo sul Trattamento dei Dati con Servio

## Responsabilità di Servio (Processor)

Servio come Responsabile deve:

1. Trattare i dati solo su istruzioni del Titolare
2. Implementare misure di sicurezza (crittografia password, tenant isolation, rate limiting)
3. Minimizzare i dati raccolti (sessioni anonime, PII opzionale)
4. Applicare policy di retention automatica
5. Redigere PII nei log di audit
6. Fornire export e cancellazione per account staff
7. Mettere a disposizione il registro attività di trattamento

## Flusso dati

```
Ospite → QR → Servio API → DB tenant-scoped
                                    ↓
                              Locale (accesso admin)
                                    ↓
                              Export / cancellazione su richiesta
```

## Contatti

- **Privacy (Controller):** info@seatqui.com
- **DPO / Processor:** info@seatqui.com

## Riferimenti

- [Informativa Privacy](/legal/privacy)
- [DPA](/legal/dpa)
- [Registro dati (API)](/admin/settings/privacy)
