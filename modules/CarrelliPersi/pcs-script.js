jQuery(document).ready(function ($) {

    // Funzione per validare la mail seriamente
    function validateEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // 1. Trigger: Aspettiamo 10 secondi
    setTimeout(function () {
        // Controlli di sicurezza:
        // - Non deve essere già stato mostrato (localStorage)
        // - Il carrello non deve essere vuoto (evitiamo di disturbare fantasmi)
        if (!localStorage.getItem('pcs_done') && $('.cart-empty').length === 0) {

            $('body').append(`
                <div id="pcs-banner" style="position:fixed; bottom:-100%; left:0; width:100%; background:#fff; z-index:999999; padding:25px 20px; box-shadow:0 -10px 30px rgba(0,0,0,0.2); border-radius:25px 25px 0 0; border-top: 3px solid #D4AF37; text-align:center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; transition: bottom 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                    <div style="max-width:400px; margin:0 auto;">
                        <p style="margin:0 0 10px 0; font-size:18px; font-weight:bold; color:#333; line-height:1.2;">
                            Vuoi completare l'acquisto più tardi?
                        </p>
                        <p style="margin:0 0 20px 0; font-size:14px; color:#666;">
                            Ti mandiamo il link diretto al tuo carrello salvato, così non perdi i tuoi gioielli.
                        </p>

                        <input type="email" id="pcs-input-mail" placeholder="Inserisci la tua mail..."
                            style="width:100%; padding:15px; border:1px solid #ddd; border-radius:12px; margin-bottom:15px; font-size:16px; -webkit-appearance:none; outline:none; box-sizing:border-box;">

                        <button id="pcs-send-btn"
                            style="width:100%; background:#000; color:#fff; border:none; padding:16px; border-radius:12px; font-weight:bold; text-transform:uppercase; cursor:pointer; font-size:14px; letter-spacing:1px; transition: background 0.3s;">
                            Ricevi il link ora
                        </button>

                        <p id="pcs-no-thanks" style="font-size:13px; color:#bbb; margin-top:15px; cursor:pointer; text-decoration:none;">
                            No grazie, sto solo guardando
                        </p>
                    </div>
                </div>
            `);

            // Animazione di entrata "smooth"
            setTimeout(() => { $('#pcs-banner').css('bottom', '0'); }, 100);
        }
    }, 10000);

    // 2. Gestione invio mail
    $(document).on('click', '#pcs-send-btn', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const $input = $('#pcs-input-mail');
        const email = $input.val();

        if (validateEmail(email)) {
            $btn.text('Salvataggio...').css('opacity', '0.7').prop('disabled', true);

            $.post(pcs_ajax.url, {
                action: 'pcs_save_mail',
                email: email,
                nonce: pcs_ajax.nonce
            }, function (res) {
                if (res.success) {
                    $('#pcs-banner').html(`
                        <div style="padding:20px 0;">
                            <span style="font-size:40px;">✨</span>
                            <p style="font-weight:bold; color:#333; margin-top:10px;">Carrello salvato!<br>Controlla la tua casella mail.</p>
                        </div>
                    `);
                    localStorage.setItem('pcs_done', 'true');

                    // Chiudiamo tutto dopo 3 secondi
                    setTimeout(() => {
                        $('#pcs-banner').css('bottom', '-100%');
                        setTimeout(() => $('#pcs-banner').remove(), 600);
                    }, 3000);
                }
            });
        } else {
            // Feedback errore
            $input.css('border-color', '#ff4d4d').addClass('shake');
            setTimeout(() => $input.removeClass('shake'), 500);
            $input.attr('placeholder', 'Inserisci una mail valida!');
        }
    });

    // 3. Chiusura manuale
    $(document).on('click', '#pcs-no-thanks', function () {
        $('#pcs-banner').css('bottom', '-100%');
        localStorage.setItem('pcs_done', 'true');
        setTimeout(() => $('#pcs-banner').remove(), 600);
    });
});