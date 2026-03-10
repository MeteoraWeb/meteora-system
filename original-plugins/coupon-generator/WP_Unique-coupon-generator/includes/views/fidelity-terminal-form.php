<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form method="post" class="ucg-fidelity-form" data-ajax="1">
    <?php wp_nonce_field('ucg_fidelity_action','_wpnonce_ucg_fid'); ?>
    <p><input type="text" name="fid_identifier" placeholder="<?php echo esc_attr__('Email o Codice QR', 'unique-coupon-generator'); ?>" required></p>
    <p><input type="number" step="0.01" name="fid_amount" placeholder="<?php echo esc_attr__('Importo speso', 'unique-coupon-generator'); ?>"></p>
    <p>
        <select name="fid_set">
            <?php foreach($sets as $id=>$s): ?>
                <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($s['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p><input type="number" name="fid_deduct" placeholder="<?php echo esc_attr__('Punti da scalare', 'unique-coupon-generator'); ?>"></p>
    <p><button type="submit" name="ucg_fidelity_submit"><?php echo esc_html__('Assegna', 'unique-coupon-generator'); ?></button></p>
</form>
<div id="fid-qr-reader" style="width:280px;"></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
    if(typeof Html5QrcodeScanner!=="undefined" && document.getElementById('fid-qr-reader')){
        var fidScanner=new Html5QrcodeScanner('fid-qr-reader',{fps:10,qrbox:200});
        fidScanner.render(function(msg){
            const form = document.querySelector('.ucg-fidelity-form');
            const input = form ? form.querySelector('input[name="fid_identifier"]') : null;
            if(input){
                let code = msg;
                try{
                    const params = new URLSearchParams(new URL(msg).search);
                    code = params.get('coupon_code') || msg;
                }catch(e){/* not a URL */}
                input.value = code;
                const amount=form.querySelector('input[name="fid_amount"]');
                const deduct=form.querySelector('input[name="fid_deduct"]');
                if((amount && amount.value) || (deduct && deduct.value)){
                    form.submit();
                }
            }
            fidScanner.clear();
        });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var msg=document.getElementById('ucg-fid-message');
    if(msg){
        alert(msg.innerText.trim());
    }
});
</script>
