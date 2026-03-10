# Documentation

Questo spazio è riservato ai materiali di documentazione del plugin Unique Coupon Generator.

## Verifica licenza

La procedura di verifica della licenza utilizza il metodo privato `resolve_license_endpoint()` presente nella classe `UCG_Access_Gate`. L'URL del servizio remoto non è più definito come stringa in chiaro, ma è ricostruito dinamicamente partendo da segmenti codificati in Base64. Il metodo:

1. tenta di leggere un transient (`ucg_license_endpoint_cache`) che contiene l'endpoint offuscato e firmato mediante `hash_hmac` basato sul `wp_salt` locale;
2. in caso di cache non valida o assente, decodifica i segmenti, ricompone l'URL e aggiorna il transient salvando nuovamente un payload codificato;
3. restituisce l'URL risultante a `perform_remote_check()`, mantenendo inalterata la logica di query-string e la chiamata `wp_remote_get()`.

Il transient memorizza solamente un payload Base64 (hash + URL) che riduce l'esposizione del valore reale sia nel database sia in eventuali log. Durante la disinstallazione del plugin il transient viene eliminato insieme agli altri artefatti della licenza.
