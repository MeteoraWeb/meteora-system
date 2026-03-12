<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

function ucg_render_tab_coupon_pages($context = array()){
    $coupon_sets = get_option('mms_coupon_sets', []);

    echo '<section class="ucg-card ucg-card--table">';
    echo '<h2><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> ' . esc_html__('Pagine coupon generate', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Crea o modifica rapidamente le pagine pubbliche associate ai tuoi set di coupon.', 'unique-coupon-generator') . '</p>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>' . esc_html__('Shortcode', 'unique-coupon-generator') . '</th><th>' . esc_html__('Descrizione', 'unique-coupon-generator') . '</th><th>' . esc_html__('Azioni', 'unique-coupon-generator') . '</th></tr></thead><tbody>';
    if(!empty($coupon_sets)){
        foreach($coupon_sets as $set){
            $shortcode = '[richiedi_coupon base="'.$set['name'].'"]';
            $desc = sprintf(__('Modulo richiesta coupon per il set %s', 'unique-coupon-generator'), esc_html($set['name']));
            $slug = sanitize_title('richiedi-'.$set['name']);
            $exists = get_page_by_path($slug);
            echo '<tr><td><code>'.esc_html($shortcode).'</code></td><td>'.esc_html($desc).'</td><td>';
            if($exists){
                $edit_link = get_edit_post_link($exists->ID);
                $view_link = get_permalink($exists);
                if($edit_link){
                    echo '<a href="'.esc_url($edit_link).'" class="button button-secondary">'.esc_html__('Modifica pagina', 'unique-coupon-generator').'</a> ';
                }
                if($view_link){
                    echo '<a href="'.esc_url($view_link).'" class="button" target="_blank" rel="noopener noreferrer">'.esc_html__('APRI LINK', 'unique-coupon-generator').'</a>';
                }
            }else{
                $url = ucg_get_shortcode_page_creation_url($slug, $shortcode, $set['name']);
                echo '<a href="'.esc_url($url).'" class="button button-primary">'.esc_html__('Crea pagina', 'unique-coupon-generator').'</a>';
            }
            echo '</td></tr>';
        }
    }else{
        echo '<tr><td colspan="3">' . esc_html__('Nessun set di coupon disponibile.', 'unique-coupon-generator') . '</td></tr>';
    }
    $grid_shortcode = '[ucg_coupon_sets]';
    $grid_desc = __('Griglia dei set di coupon attivi', 'unique-coupon-generator');
    $grid_slug = 'set-coupon-attivi';
    $grid_page = get_page_by_path($grid_slug);
    echo '<tr><td><code>'.esc_html($grid_shortcode).'</code></td><td>'.esc_html($grid_desc).'</td><td>';
    if($grid_page){
        $edit_link = get_edit_post_link($grid_page->ID);
        $view_link = get_permalink($grid_page);
        if($edit_link){
            echo '<a href="'.esc_url($edit_link).'" class="button button-secondary">'.esc_html__('Modifica pagina', 'unique-coupon-generator').'</a> ';
        }
        if($view_link){
            echo '<a href="'.esc_url($view_link).'" class="button" target="_blank" rel="noopener noreferrer">'.esc_html__('APRI LINK', 'unique-coupon-generator').'</a>';
        }
    }else{
        $grid_url = ucg_get_shortcode_page_creation_url($grid_slug, $grid_shortcode, $grid_slug);
        echo '<a href="'.esc_url($grid_url).'" class="button button-primary">'.esc_html__('Crea pagina', 'unique-coupon-generator').'</a>';
    }
    echo '</td></tr>';

    $verify_shortcode = '[verifica_coupon]';
    $verify_desc = __('Pagina pubblica per la verifica dei coupon generati', 'unique-coupon-generator');
    $verify_slug = 'verifica-coupon';
    $verify_page = get_page_by_path($verify_slug);
    echo '<tr><td><code>'.esc_html($verify_shortcode).'</code></td><td>'.esc_html($verify_desc).'</td><td>';
    if($verify_page){
        $edit_link = get_edit_post_link($verify_page->ID);
        $view_link = get_permalink($verify_page);
        if($edit_link){
            echo '<a href="'.esc_url($edit_link).'" class="button button-secondary">'.esc_html__('Modifica pagina', 'unique-coupon-generator').'</a> ';
        }
        if($view_link){
            echo '<a href="'.esc_url($view_link).'" class="button" target="_blank" rel="noopener noreferrer">'.esc_html__('APRI LINK', 'unique-coupon-generator').'</a>';
        }
    }else{
        $verify_title = __('Verifica Coupon', 'unique-coupon-generator');
        $verify_url = ucg_get_shortcode_page_creation_url($verify_slug, $verify_shortcode, $verify_title);
        echo '<a href="'.esc_url($verify_url).'" class="button button-primary">'.esc_html__('Crea pagina', 'unique-coupon-generator').'</a>';
    }
    echo '</td></tr>';
    echo '</tbody></table>';
    echo '</section>';
}

function ucg_render_tab_shortcodes($context = array()){
    $extra=[
        ['tag'=>'[verifica_coupon]','desc'=>__('Form per verificare i coupon', 'unique-coupon-generator'),'slug'=>'verifica-coupon'],
        ['tag'=>'[ucg_fidelity_terminal]','desc'=>__('Terminale punti fidelity', 'unique-coupon-generator'),'slug'=>'fidelity-terminal'],
        ['tag'=>'[ucg_fidelity_points]','desc'=>__('Modulo visualizzazione punti', 'unique-coupon-generator'),'slug'=>'fidelity-points'],
        ['tag'=>'[verifica_ticket]','desc'=>__('Verifica e convalida i ticket evento', 'unique-coupon-generator'),'slug'=>'verifica-ticket'],
        ['tag'=>'[ticket_pr]','desc'=>__('Gestione pagamenti ticket per PR', 'unique-coupon-generator'),'slug'=>'ticket-pr'],
    ];

    echo '<section class="ucg-card ucg-card--table">';
    echo '<h2><span class="dashicons dashicons-editor-code" aria-hidden="true"></span> ' . esc_html__('Shortcode disponibili', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Utilizza questi shortcode per creare pagine personalizzate o integrare le funzionalità del plugin.', 'unique-coupon-generator') . '</p>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>' . esc_html__('Shortcode', 'unique-coupon-generator') . '</th><th>' . esc_html__('Descrizione', 'unique-coupon-generator') . '</th><th>' . esc_html__('Azione', 'unique-coupon-generator') . '</th></tr></thead><tbody>';
    foreach($extra as $e){
        $exists=get_page_by_path($e['slug']);
        echo '<tr><td><code>'.esc_html($e['tag']).'</code></td><td>'.esc_html($e['desc']).'</td><td>';
        if($exists){
            echo '<a href="'.esc_url(get_edit_post_link($exists->ID)).'" class="button button-secondary">'.esc_html__('Modifica pagina', 'unique-coupon-generator').'</a>';
        }else{
            $url = ucg_get_shortcode_page_creation_url($e['slug'], $e['tag'], $e['slug']);
            echo '<a href="'.esc_url($url).'" class="button button-primary">'.esc_html__('Crea pagina', 'unique-coupon-generator').'</a>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    echo '</section>';
}

function ucg_display_shortcodes_page(){
    ucg_render_tab_shortcodes();
}

function ucg_get_shortcode_page_creation_url($slug, $shortcode, $title = ''){
    $slug       = sanitize_title($slug);
    $shortcode  = (string) $shortcode;
    $title      = is_string($title) ? $title : '';
    $redirect   = ucg_get_shortcode_creation_redirect_target();

    $args = array(
        'action'       => 'ucg_create_shortcode_page',
        'create'       => $slug,
        'shortcode'    => $shortcode,
        'ucg_redirect' => $redirect,
    );

    if($title !== ''){
        $args['title'] = $title;
    }

    $url = add_query_arg($args, admin_url('admin-post.php'));

    return wp_nonce_url($url, 'ucg_create_shortcode_page');
}

function ucg_get_shortcode_creation_redirect_target(){
    $base  = admin_url('admin.php');
    $query = array('page' => 'ucg-shortcodes');

    foreach($_GET as $key => $value){
        if(in_array($key, array('create', 'shortcode', 'title', '_wpnonce', 'ucg_created', 'ucg_created_ps', 'ucg_redirect'), true)){
            continue;
        }

        if($key === 'page'){
            $query['page'] = sanitize_key(wp_unslash($value));
            continue;
        }

        $sanitized_key = sanitize_key($key);
        if($sanitized_key === ''){
            continue;
        }

        if(is_array($value)){
            $query[$sanitized_key] = array_map('sanitize_text_field', wp_unslash($value));
        }else{
            $query[$sanitized_key] = sanitize_text_field(wp_unslash($value));
        }
    }

    $redirect = add_query_arg($query, $base);

    return esc_url_raw($redirect);
}

function ucg_handle_shortcode_page_create(){
    if(!current_user_can('manage_options') && !current_user_can('edit_pages')){
        wp_die(esc_html__('Non hai il permesso di eseguire questa azione.', 'unique-coupon-generator'));
    }

    check_admin_referer('ucg_create_shortcode_page');

    $slug       = sanitize_title(wp_unslash($_REQUEST['create'] ?? ''));
    $shortcode  = wp_unslash($_REQUEST['shortcode'] ?? '');
    $title      = sanitize_text_field(wp_unslash($_REQUEST['title'] ?? ''));
    $raw_redirect = isset($_REQUEST['ucg_redirect']) ? esc_url_raw(wp_unslash($_REQUEST['ucg_redirect'])) : '';

    if($slug === '' || $shortcode === ''){
        $redirect_url = ucg_resolve_shortcode_creation_redirect($raw_redirect, array(
            'ucg_created_error' => 'invalid_request',
        ));
        wp_safe_redirect($redirect_url);
        exit;
    }

    if(get_page_by_path($slug)){
        $redirect_url = ucg_resolve_shortcode_creation_redirect($raw_redirect, array(
            'ucg_created_error' => 'existing_page',
        ));
        wp_safe_redirect($redirect_url);
        exit;
    }

    $post_status = current_user_can('publish_pages') ? 'publish' : 'draft';

    $page_id = wp_insert_post(array(
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_type'    => 'page',
        'post_status'  => $post_status,
        'post_author'  => get_current_user_id(),
        'post_content' => wp_kses_post($shortcode),
    ), true);

    if(is_wp_error($page_id)){
        $message = sanitize_text_field($page_id->get_error_message());
        $extra   = array('ucg_created_error' => 'insertion_failed');

        if($message !== ''){
            $extra['ucg_created_msg'] = $message;
        }

        $redirect_url = ucg_resolve_shortcode_creation_redirect($raw_redirect, $extra);
        wp_safe_redirect($redirect_url);
        exit;
    }

    $redirect_url = ucg_resolve_shortcode_creation_redirect($raw_redirect, array(
        'ucg_created'    => $page_id,
        'ucg_created_ps' => $post_status,
    ));

    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_ucg_create_shortcode_page', 'ucg_handle_shortcode_page_create');

function ucg_resolve_shortcode_creation_redirect($raw_redirect, $extra_query = array()){
    $fallback = add_query_arg('page', 'ucg-shortcodes', admin_url('admin.php'));
    $target   = $fallback;

    if(is_string($raw_redirect) && $raw_redirect !== ''){
        $validated = wp_validate_redirect($raw_redirect, false);
        if(is_string($validated) && strpos($validated, admin_url()) === 0){
            $target = $validated;
        }
    }else{
        $referer = wp_get_referer();
        if(is_string($referer)){
            $validated = wp_validate_redirect($referer, false);
            if(is_string($validated) && strpos($validated, admin_url()) === 0){
                $target = $validated;
            }
        }
    }

    $redirect_url = remove_query_arg(
        array('create', 'shortcode', 'title', '_wpnonce', 'ucg_created', 'ucg_created_ps', 'ucg_redirect', 'ucg_created_error', 'ucg_created_msg'),
        $target
    );

    if(!empty($extra_query)){
        $redirect_url = add_query_arg($extra_query, $redirect_url);
    }

    return $redirect_url;
}

function ucg_render_shortcode_creation_notices(){
    if(!empty($_GET['ucg_created_error'])){
        $error_code = sanitize_key(wp_unslash($_GET['ucg_created_error']));
        $messages   = array();

        switch($error_code){
            case 'invalid_request':
                $messages[] = esc_html__('Impossibile creare la pagina: dati non validi.', 'unique-coupon-generator');
                break;
            case 'existing_page':
                $messages[] = esc_html__('Esiste già una pagina con questo slug.', 'unique-coupon-generator');
                break;
            case 'insertion_failed':
                $messages[] = esc_html__('Impossibile creare la pagina a causa di un errore interno.', 'unique-coupon-generator');
                break;
        }

        if(!empty($_GET['ucg_created_msg'])){
            $messages[] = sanitize_text_field(wp_unslash($_GET['ucg_created_msg']));
        }

        if(!empty($messages)){
            printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html(implode(' ', $messages)));
        }
    }

    if(!empty($_GET['ucg_created'])){
        $page_id = absint($_GET['ucg_created']);
        if($page_id > 0){
            $edit_link = current_user_can('edit_post', $page_id) ? get_edit_post_link($page_id) : '';
            $view_link = get_permalink($page_id);
            $messages  = array();

            $created_status = isset($_GET['ucg_created_ps']) ? sanitize_key(wp_unslash($_GET['ucg_created_ps'])) : '';

            if($created_status === 'draft'){
                $messages[] = esc_html__('La pagina è stata creata come bozza perché il tuo account non può pubblicare direttamente.', 'unique-coupon-generator');
            }

            $messages[] = esc_html__('Pagina creata con successo.', 'unique-coupon-generator');

            if($edit_link){
                $messages[] = sprintf(
                    '<a href="%s" class="button button-secondary">%s</a>',
                    esc_url($edit_link),
                    esc_html__('Modifica pagina', 'unique-coupon-generator')
                );
            }

            if($view_link){
                $messages[] = sprintf(
                    '<a href="%s" class="button" target="_blank" rel="noopener noreferrer">%s</a>',
                    esc_url($view_link),
                    esc_html__('Visualizza pagina', 'unique-coupon-generator')
                );
            }

            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', wp_kses_post(implode(' ', $messages)));
        }
    }
}
add_action('admin_notices', 'ucg_render_shortcode_creation_notices');

function ucg_add_shortcodes_submenu(){
    ucg_safe_add_submenu_page(
        null,
        __('Shortcode', 'unique-coupon-generator'),
        __('Shortcode', 'unique-coupon-generator'),
        'manage_options',
        'ucg-shortcodes',
        'ucg_display_shortcodes_page'
    );
}
add_action('admin_menu','ucg_add_shortcodes_submenu');

function ucg_render_tab_shortcodes_overview($context = array()){
    ucg_render_tab_shortcodes($context);
}
