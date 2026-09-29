# Ruoli Controller / Processor

**Ultimo aggiornamento:** 10 agosto 2026

## Panoramica

Seatqui opera come piattaforma multi-tenant. I ruoli GDPR variano in base al tipo di dato e al soggetto interessato.

## Matrice dei ruoli

| Scenario | Titolare (Controller) | Responsabile (Processor) |
|----------|----------------------|--------------------------|
| Dati staff del locale | Locale (tenant) | Seatqui |
| Dati ospiti che ordinano via QR | Locale (tenant) | Seatqui |
| Dati account admin Seatqui (super_admin) | Seatqui | — |
| Dati registrazione tenant (ragione sociale) | Locale (tenant) | Seatqui (per onboarding) |
| Log di audit piattaforma | Seatqui | Sub-responsabili hosting |

## Responsabilità del Titolare (ristorante)

Il cliente business (ristorante) come Titolare deve:

1. **Informare gli ospiti** — fornire un'informativa privacy del proprio locale (Seatqui fornisce il modello)
2. **Base giuridica** — definire la base giuridica per il trattamento dati ospiti (tipicamente legittimo interesse o contratto)
3. **Diritti interessati** — gestire richieste degli ospiti; Seatqui fornisce strumenti tecnici (anonimizzazione sessione)
4. **DPA** — firmare l'Accordo sul Trattamento dei Dati con Seatqui

## Responsabilità di Seatqui (Processor)

Seatqui come Responsabile deve:

1. Trattare i dati solo su istruzioni del Titolare
2. Implementare misure di sicurezza (crittografia password, tenant isolation, rate limiting)
3. Minimizzare i dati raccolti (sessioni anonime, PII opzionale)
4. Applicare policy di retention automatica
5. Redigere PII nei log di audit
6. Fornire export e cancellazione per account staff
7. Mettere a disposizione il registro attività di trattamento

## Flusso dati

```
Ospite → QR → Seatqui API → DB tenant-scoped
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
