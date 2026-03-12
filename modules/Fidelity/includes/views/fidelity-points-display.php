<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form method="post" class="ucg-points-form" data-ajax="1">
    <?php wp_nonce_field('mms_fidelity_points','_wpnonce_ucg_pts'); ?>
    <p><input type="text" name="fid_identifier" placeholder="<?php echo esc_attr__('Email o Codice QR', 'unique-coupon-generator'); ?>" required></p>
    <p><button type="submit" name="ucg_points_submit"><?php echo esc_html__('Verifica', 'unique-coupon-generator'); ?></button></p>
</form>
<div id="pts-qr-reader" style="width:280px;"></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
    if(typeof Html5QrcodeScanner!=="undefined" && document.getElementById('pts-qr-reader')){
        var ptsScanner=new Html5QrcodeScanner('pts-qr-reader',{fps:10,qrbox:200});
        ptsScanner.render(function(msg){
            const form=document.querySelector('.ucg-points-form');
            const input=form ? form.querySelector('input[name="fid_identifier"]') : null;
            if(input){
                let code = msg;
                try{
                    const params = new URLSearchParams(new URL(msg).search);
                    code = params.get('coupon_code') || msg;
                }catch(e){/* not a URL */}
                input.value = code;
                form.submit();
            }
            ptsScanner.clear();
        });
    }
});
</script>
<?php if(isset($points)): ?>
    <div id="ucg-fid-message" class="updated"><p><?php echo sprintf(esc_html__('Hai %d punti attivi', 'unique-coupon-generator'), esc_html($points)); ?></p></div>
    <ul>
        <?php foreach($log as $row): ?>
            <li><?php echo ($row->points>0?'+':'').$row->points; ?> <?php echo esc_html__('punti', 'unique-coupon-generator'); ?> - <?php echo esc_html($row->action); ?> - <?php echo esc_html(date('d/m/Y',strtotime($row->created_at))); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var msg=document.getElementById('ucg-fid-message');
    if(msg){
        alert(msg.innerText.trim());
    }
});
</script>
