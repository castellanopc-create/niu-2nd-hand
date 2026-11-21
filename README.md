# NIU 2nd Hand — prototipo

Valutazione dell'usato NIU da parte dei dealer, incentivi NIU, annunci con foto e vetrina pubblica / feed per global.niu.com.
PHP 8.1+ e SQLite, senza dipendenze esterne.

## Flusso cliente (pubblico, senza account)
1. `?p=stima`: telaio, modello, km e domande semplici → **classe stimata** e **supporto extra** se acquista un NIU nuovo.
2. Sceglie il **dealer** e uno **slot libero** → riceve un link personale (`?p=richiesta&t=…`) per seguire o annullare l'appuntamento.
3. Il dealer conferma/annulla da **Appuntamenti** e, in officina, avvia la valutazione ufficiale con le risposte del cliente precompilate.
   I dati del portale NIU non vengono mai mostrati al cliente.

## Flusso dealer
1. **Dealer** inserisce il telaio → dati dal portale NIU (garanzie, tagliandi, campagne tecniche).
2. Compila la **checklist NIU** + SOH batteria → punteggio e classe A/B/C/D (alcune voci sono bloccanti).
3. Vede l'**extra NIU**: ritiro semplice oppure ritiro + acquisto di un NIU nuovo (supporto maggiore NIU + dealer).
4. Registra il ritiro → **crea l'annuncio** (foto, descrizione, prezzo, località) → lo invia a NIU.
5. **Team NIU** approva o rimanda indietro con una nota → l'annuncio va nella vetrina `?p=usato` e nel feed JSON `?p=feed`.

## Struttura
```
public/          servito dal sandbox (index.php + assets)
app/             logica, NON pubblica
  config.php     importi incentivi, soglie classi, limiti foto  ← DA VALIDARE CON NIU
  checklist.php  voci della checklist e pesi                    ← BOZZA DA VALIDARE
  portal.php     lookup telaio — oggi SIMULATO, da collegare all'API del portale NIU
  models.php     modelli NIU per categoria (scooter e moto; monopattini esclusi)
shared/          creato in automatico: app.db (SQLite) e uploads/ (foto). Persistente sul sandbox, escluso da git.
```

## Primo avvio
Apri il sito: compare **Primo avvio**, crei l'account amministratore NIU, poi da **Dealer** crei gli account dei concessionari
(la password iniziale viene mostrata una sola volta).

## In locale
```
php -S localhost:8000 -t public
```

## Limiti noti del prototipo
- Dati portale **simulati**: non usare per decisioni reali.
- Nessun reset password self-service (l'admin disattiva e ricrea l'utente).
- Paese del feed fisso a `IT`, valuta EUR.
- Nessuna email automatica: cliente e dealer seguono lo stato dalla pagina. Gli orari sono uguali per tutti i dealer (`config.php`).
- Testo del consenso privacy da sostituire con l'informativa ufficiale NIU.
