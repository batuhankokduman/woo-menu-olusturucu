<?php
/**
 * Plugin Name: Woo Ürün Kategorilerinden Menü Oluşturucu
 * Description: WooCommerce ürün kategorilerinden hiyerarşik yapıda, özelleştirilebilir filtrelerle otomatik menü oluşturur. Modern arayüz, türkçe destek ve güvenli kod yapısıyla tasarlanmıştır.
 * Version: 1.3
 * Author: Batuhan Kökduman
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-menu-olusturucu
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'woo_menu_olusturucu_admin_sayfa');

function woo_menu_olusturucu_admin_sayfa() {
    add_menu_page(
        'Woo Menü Oluşturucu',
        'Woo Menü Oluşturucu',
        'manage_options',
        'woo-menu-olusturucu',
        'woo_menu_olusturucu_ekran',
        'dashicons-list-view',
        60
    );
}

function woo_menu_olusturucu_ekran() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Bu sayfayı görüntüleme yetkiniz yok.', 'woo-menu-olusturucu'));
    }

    echo '<div class="wrap" style="max-width:800px;margin-top:40px;background:#fff;padding:30px;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,0.05);">';
    echo '<h1 style="font-size:24px;margin-bottom:20px;">Woo Ürün Kategorilerinden Menü Oluşturucu</h1>';
    echo '<p style="margin-bottom:20px;color:#555;">Bu aracı kullanarak WooCommerce ürün kategorilerinizden otomatik menü oluşturabilirsiniz. Alt kategori derinliği, minimum alt kategori sayısı gibi ayarları belirleyebilirsiniz.</p>';

    echo '<form method="post">';
    wp_nonce_field('woo_menu_olustur_action', 'woo_menu_olustur_nonce');
    echo '<table class="form-table">';

    echo '<tr><th scope="row"><label for="menu_adi">Menü Adı</label></th>';
    echo '<td><input name="menu_adi" type="text" id="menu_adi" class="regular-text" required></td></tr>';

    echo '<tr><th scope="row">Hiyerarşik Eklemeyi Aktifleştir</th>';
    echo '<td><label><input type="checkbox" name="hiyerarsi_aktif" checked> Alt kategorileri iç içe ekle</label></td></tr>';

    echo '<tr><th scope="row"><label for="min_alt_kategori">Minimum Alt Kategori Sayısı</label></th>';
    echo '<td><input name="min_alt_kategori" type="number" id="min_alt_kategori" value="0" min="0" class="small-text"> <span style="color:#777">* Sadece 1. düzey alt kategoriler sayılır</span></td></tr>';

    echo '</table>';
    echo '<p class="submit"><input type="submit" name="menu_olustur" class="button button-primary" value="Menü Oluştur"></p>';
    echo '</form>';
    echo '</div>';

    if (isset($_POST['menu_olustur'])) {
        if (!isset($_POST['woo_menu_olustur_nonce']) || !wp_verify_nonce($_POST['woo_menu_olustur_nonce'], 'woo_menu_olustur_action')) {
            wp_die('Geçersiz veya süresi dolmuş form isteği. Lütfen sayfayı yenileyin ve tekrar deneyin.');
        }

        woo_menu_olusturucu_islem_yap();
    }
}

function woo_menu_olusturucu_islem_yap() {
    $menu_adi = sanitize_text_field($_POST['menu_adi']);
    $hiyerarsi = !empty($_POST['hiyerarsi_aktif']);
    $min_alt_kategori = intval($_POST['min_alt_kategori']);

    $menu_id = wp_create_nav_menu($menu_adi);

    if (is_wp_error($menu_id)) {
        echo '<div class="notice notice-error"><p>Menü oluşturulamadı. Belki aynı isimde bir menü zaten vardır.</p></div>';
        return;
    }

    $kategoriler = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'parent' => 0
    ]);

    foreach ($kategoriler as $kategori) {
        $ilk_seviye_alt_kategoriler = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $kategori->term_id
        ]);

        if ($min_alt_kategori > 0 && count($ilk_seviye_alt_kategoriler) < $min_alt_kategori) {
            continue;
        }

        $menu_item_id = wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => $kategori->name,
            'menu-item-url' => get_term_link($kategori),
            'menu-item-status' => 'publish',
            'menu-item-type' => 'custom',
        ]);

        if ($hiyerarsi) {
            woo_menu_kategori_ekle_recursive($kategori->term_id, $menu_id, $menu_item_id);
        }
    }

    echo '<div class="notice notice-success"><p><strong>' . esc_html($menu_adi) . '</strong> menüsü başarıyla oluşturuldu.</p></div>';
}

function woo_menu_kategori_ekle_recursive($parent_id, $menu_id, $ust_menu_item_id) {
    $alt_kategoriler = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'parent' => $parent_id
    ]);

    foreach ($alt_kategoriler as $alt_kat) {
        $yeni_item_id = wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => $alt_kat->name,
            'menu-item-url' => get_term_link($alt_kat),
            'menu-item-status' => 'publish',
            'menu-item-type' => 'custom',
            'menu-item-parent-id' => $ust_menu_item_id
        ]);

        woo_menu_kategori_ekle_recursive($alt_kat->term_id, $menu_id, $yeni_item_id);
    }
}
