<?php
/**
 * Plugin Name: Webklient Woo Heureka Thank You Page Integration
 * Description: Vloží Heureka.cz skript na děkovnou stránku WooCommerce s údaji o nákupu.
 * Version: 1.2
 * Author: Michal Kubíček, Webklient.cz
 * Text Domain: webklient-heureka
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Zamezení přímému přístupu
}

// Přidá Heureka.cz skript na děkovnou stránku
add_action('woocommerce_thankyou', 'insert_heureka_thank_you_script', 10, 1);

function insert_heureka_thank_you_script($order_id) {
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }

    // Získání uloženého klíče z databáze
    $heureka_key = get_option('heureka_api_key', '');

    if (empty($heureka_key)) {
        wc_get_logger()->error('Heureka API klíč nebyl zadán.', array('source' => 'webklient-heureka'));
        return;
    }

    $order_total = $order->get_total();
    $currency = $order->get_currency();

    // Generování inline skriptu
    $script = <<<EOT
    <!-- Heureka.cz THANK YOU PAGE script -->
    <script>
        (function(t, r, a, c, k, i, n, g) {
            t['ROIDataObject'] = k;
            t[k] = t[k] || function() {
                (t[k].q = t[k].q || []).push(arguments)
            }, t[k].c = i;
            n = r.createElement(a), g = r.getElementsByTagName(a)[0];
            n.async = 1; n.src = c;
            g.parentNode.insertBefore(n, g)
        })(window, document, 'script', '//www.heureka.cz/ocm/sdk.js?version=2&page=thank_you', 'heureka', 'cz');

        heureka('authenticate', '{$heureka_key}');
        heureka('set_order_id', '{$order_id}');
    EOT;

    // Přidání produktů
    foreach ($order->get_items() as $item) {
        $product_name = esc_js($item->get_name());
        $product_price = wc_get_price_including_tax($item->get_product(), ['qty' => 1]);
        $product_quantity = $item->get_quantity();
        $script .= "heureka('add_product', '" . esc_js($item->get_product_id()) . "', '{$product_name}', '{$product_price}', '{$product_quantity}');\n";
    }

    $script .= "heureka('set_total_vat', '{$order_total}');\n";
    $script .= "heureka('set_currency', '{$currency}');\n";
    $script .= "heureka('send', 'Order');\n";
    $script .= '</script><!-- End Heureka.cz THANK YOU PAGE script -->';

    // Vložení inline skriptu do stránky
    echo $script;
}

// Vytvoření administrační stránky pro vložení Heureka klíče
add_action('admin_menu', 'heureka_create_menu');

function heureka_create_menu() {
    add_management_page(
        __('Heureka API Nastavení', 'webklient-heureka'), // Název stránky
        __('Heureka API', 'webklient-heureka'), // Název v menu
        'manage_options', // Právo k zobrazení
        'heureka-api-settings', // Slug stránky
        'heureka_settings_page' // Funkce, která stránku vykreslí
    );
}

// Vykreslení nastavení stránky
function heureka_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Nastavení Heureka API klíče', 'webklient-heureka'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('heureka-settings-group');
            do_settings_sections('heureka-api-settings');
            submit_button();
            ?>
        </form>
        <p><?php _e('Naleznete svůj API klíč na', 'webklient-heureka'); ?> <a href="<?php echo esc_url('https://sluzby.heureka.cz/statistics-and-reports/conversion-measurement'); ?>" target="_blank">https://sluzby.heureka.cz/statistics-and-reports/conversion-measurement</a>.</p>
    </div>
    <?php
}

// Registrace nastavení
add_action('admin_init', 'heureka_register_settings');

function heureka_register_settings() {
    register_setting('heureka-settings-group', 'heureka_api_key');

    add_settings_section(
        'heureka_section',
        __('Webklient.cz Heureka Děkovná stránka', 'webklient-heureka'),
        null,
        'heureka-api-settings'
    );

    add_settings_field(
        'heureka_api_key',
        __('Heureka API Klíč', 'webklient-heureka'),
        'heureka_api_key_field_callback',
        'heureka-api-settings',
        'heureka_section'
    );
}

// Vykreslení input pole pro API klíč
function heureka_api_key_field_callback() {
    $heureka_key = get_option('heureka_api_key', '');
    echo '<input type="text" name="heureka_api_key" value="' . esc_attr($heureka_key) . '" size="50">';
    echo '<p class="description">' . __('Najděte svůj klíč na', 'webklient-heureka') . ' <a href="' . esc_url('https://sluzby.heureka.cz/statistics-and-reports/conversion-measurement') . '" target="_blank">https://sluzby.heureka.cz/statistics-and-reports/conversion-measurement</a>.</p>';
}

// Přidání odkazu na nastavení na stránku přehledu pluginů
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'heureka_plugin_action_links');

function heureka_plugin_action_links($links) {
    $settings_link = '<a href="tools.php?page=heureka-api-settings">' . __('Nastavení', 'webklient-heureka') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
