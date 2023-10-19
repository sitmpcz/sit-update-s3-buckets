<?php
/**
 * Plugin Name: SIT update S3 buckets
 * Description: Update S3 paths after moving to new S3 buckets
 * Version: 1.0.1
 * Author: SIT:Jaroslav Dvořák
 **/

if ( !defined('SCN_PLUGIN_PATH') ) {
    define( 'SCN_PLUGIN_PATH', plugin_dir_url( __FILE__ ) );
}

// Odkaz na stranku naseho nastaveni v admin
// Submenu Hlavniho nastaveni
add_action( 'admin_menu', 'sits3_add_admin_plugin_menu' );

function sits3_add_admin_plugin_menu():void {

    add_submenu_page(
        'options-general.php',
        'SIT Update S3 Bucket',
        'SIT Update S3 Bucket',
        'administrator',
        'sit-update-bucket-settings',
        'sits3_add_admin_plugin_page'
    );

    //call register settings function
    add_action( 'admin_init', 'sits3_register_plugin_settings' );
}

function sits3_register_plugin_settings():void {

    register_setting( "sits3_options", "sits3_old_bucket" );
}

function sits3_add_admin_plugin_page():void {

    if ( isset( $_GET['_wpnonce'], $_GET['action'] ) ) {

        $action = sanitize_key( $_GET['action'] );
        $nonce = sanitize_key( $_GET['_wpnonce'] );

        // Nonce verification.
        if ( $action === "sits3" && wp_verify_nonce( $nonce, $action ) ) {
            $html = sits3_do_it();
            require_once __DIR__ . "/views/ok.php";
        }
    }
    else {
        require_once __DIR__ . "/views/admin-option-page.php";
    }
}

function sits3_do_it():string {

    global $wpdb;

    $html = "";

    // Pokud pouzijeme tento plugin, na 99% by tam WPMF_AWS3_SETTINGS mela byt definovana
    if ( sits3_check_is_s3() === true ) {
        // Settings
        $settings = sits3_get_s3_settings();

        if ( !$settings ) {
            return "Není co řešit";
        }

        $new_bucket = $settings["bucket"];
        $old_bucket = $settings["sits3_old_bucket"];

        // 1. Option - _wpmfAddon_aws3_config

        $result = $wpdb->get_results("SELECT option_value FROM {$wpdb->options} WHERE option_name = '_wpmfAddon_aws3_config'");
        // Pokud najdeme aktualizujeme
        if ( $result ) {
            // Mame object
            $option = unserialize( array_shift($result)->option_value );
            // Zmenime nazev bucketu
            $option["bucket"] = $new_bucket;
            // Udelame update
            if ( update_option( "_wpmfAddon_aws3_config", $option ) ) {
                $html .= '<p>1. Option <em>_wpmfAddon_aws3_config</em> <b>aktualizováno</b>.</p>';
            }
            // Update delate nemusime
            else {
                $html .= '<p>1. Option <em>_wpmfAddon_aws3_config</em> <b>není třeba aktualizovat</b>.</p>';
            }
        }

        // 2. Posts - guid

        $query = $wpdb->prepare( "UPDATE {$wpdb->posts} SET guid = REPLACE(guid, %s, %s)", $old_bucket, $new_bucket );
        $result = $wpdb->query( $query );
        $html .= '<p>2. Tabulka: <b>posts</b>, sloupec <b>guid</b>, '.$result.' záznamů bylo aktualizováno.</p>';

        // 2b. neobsahuje nahodou spatnou variantu adresy ...//var/www...?

        $query = $wpdb->prepare( "UPDATE {$wpdb->posts} SET guid = REPLACE(guid, %s, %s)", '//var/', '/var/' );
        $result = $wpdb->query( $query );
        $html .= '<p>2b. Tabulka: <b>posts</b>, sloupec <b>guid</b>, špatné adresy se dvěma lomítkama, '.$result.' záznamů bylo aktualizováno.</p>';

        // 3. Posts - post_content

        $query = $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $old_bucket, $new_bucket );
        $result = $wpdb->query( $query );
        $html .= '<p>3. Tabulka: <b>posts</b>, sloupec <b>post_content</b>, '.$result.' záznamů bylo aktualizováno.</p>';

        // 3b. neobsahuje nahodou spatnou variantu adresy ...//var/www...?

        $query = $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", '//var/', '/var/' );
        $result = $wpdb->query( $query );
        $html .= '<p>3b. Tabulka: <b>posts</b>, sloupec <b>post_content</b>, špatné adresy se dvěma lomítkama, '.$result.' záznamů bylo aktualizováno.</p>';

        // 4. Postmeta - meta_value

        // Vsechny postmeta kde se vyskytuje nazev bucketu
        $result = $wpdb->get_results("SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE '%{$old_bucket}%'");
        $count = 0;
        if ( $result ) {
            // Update kazdyho zaznamu
            foreach ( $result as $item ) {

                $update = 0;

                // Pokud je to wpmf_awsS3_info
                if ( $item->meta_key == 'wpmf_awsS3_info' ) {

                    $array = unserialize( $item->meta_value );
                    $array["Bucket"] = $new_bucket;

                    $new_key = str_replace( '//var/', '/var/', $array["Key"] );

                    if ( $new_key ) {
                        $array["Key"] = $new_key;
                    }

                    $meta_value = serialize( $array );

                    $update = $wpdb->update( $wpdb->prefix . 'postmeta', [ 'meta_value' => $meta_value ], [ 'meta_id' => $item->meta_id ] );
                }

                if ( $update ) {
                    $count += $update;
                }
            }
        }
        $html .= '<p>4. Tabulka: <b>postmeta</b>, sloupec <b>meta_value</b>, '.$count.' záznamů bylo aktualizováno.</p>';

        // 5. wpmf_s3_queue - destination

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}wpmf_s3_queue SET destination = REPLACE(destination, %s, %s)", $old_bucket, $new_bucket );
        $result = $wpdb->query( $query );
        $html .= '<p>5. Tabulka: <b>d3s_wpmf_s3_queue</b>, sloupec <b>destination</b>, '.$result.' záznamů bylo aktualizováno.</p>';

        // 5b. neobsahuje nahodou spatnou variantu adresy ...//var/www...?

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}wpmf_s3_queue SET destination = REPLACE(destination, %s, %s)", '//var/', '/var/' );
        $result = $wpdb->query( $query );
        $html .= '<p>5b. Tabulka: <b>wpmf_s3_queue</b>, sloupec <b>destination</b>, špatné adresy se dvěma lomítkama, '.$result.' záznamů bylo aktualizováno.</p>';

        // 6. yoast_indexable - permalink, twitter_image, open_graph_image, open_graph_image_meta

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET permalink = REPLACE(permalink, %s, %s)", $old_bucket, $new_bucket );
        $result = $wpdb->query( $query );
        $html .= '<p>6. Tabulka: <b>yoast_indexable</b>, sloupec <b>permalink</b>, '.$result.' záznamů bylo aktualizováno.</p>';

        // 6b. neobsahuje nahodou spatnou variantu adresy ...//var/www...?

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET permalink = REPLACE(permalink, %s, %s)", '//var/', '/var/' );
        $result = $wpdb->query( $query );
        $html .= '<p>6b. Tabulka: <b>yoast_indexable</b>, sloupec <b>permalink</b>, špatné adresy se dvěma lomítkama, '.$result.' záznamů bylo aktualizováno.</p>';

        // 7. yoast_indexable - permalink, twitter_image, open_graph_image, open_graph_image_meta

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET twitter_image = REPLACE(twitter_image, %s, %s)", $old_bucket, $new_bucket );
        $result = $wpdb->query( $query );
        $html .= '<p>7. Tabulka: <b>yoast_indexable</b>, sloupec <b>twitter_image</b>, '.$result.' záznamů bylo aktualizováno.</p>';

        // 7b. neobsahuje nahodou spatnou variantu adresy ...//var/www...?

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET twitter_image = REPLACE(twitter_image, %s, %s)", '//var/', '/var/' );
        $result = $wpdb->query( $query );
        $html .= '<p>7b. Tabulka: <b>yoast_indexable</b>, sloupec <b>twitter_image</b>, špatné adresy se dvěma lomítkama, '.$result.' záznamů bylo aktualizováno.</p>';

        // 8. yoast_indexable - permalink, twitter_image, open_graph_image, open_graph_image_meta

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET open_graph_image = REPLACE(open_graph_image, %s, %s)", $old_bucket, $new_bucket );
        $result = $wpdb->query( $query );
        $html .= '<p>8. Tabulka: <b>yoast_indexable</b>, sloupec <b>open_graph_image</b>, '.$result.' záznamů bylo aktualizováno.</p>';

        // 8b. neobsahuje nahodou spatnou variantu adresy ...//var/www...?

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET open_graph_image = REPLACE(open_graph_image, %s, %s)", '//var/', '/var/' );
        $result = $wpdb->query( $query );
        $html .= '<p>8b. Tabulka: <b>yoast_indexable</b>, sloupec <b>open_graph_image</b>, špatné adresy se dvěma lomítkama, '.$result.' záznamů bylo aktualizováno.</p>';

        // 9. yoast_indexable - permalink, twitter_image, open_graph_image, open_graph_image_meta

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET open_graph_image_meta = REPLACE(open_graph_image_meta, %s, %s)", $old_bucket, $new_bucket );
        $result = $wpdb->query( $query );
        $html .= '<p>9. Tabulka: <b>yoast_indexable</b>, sloupec <b>open_graph_image_meta</b>, '.$result.' záznamů bylo aktualizováno.</p>';

        // 9b. neobsahuje nahodou spatnou variantu adresy ...//var/www...?

        $query = $wpdb->prepare( "UPDATE {$wpdb->prefix}yoast_indexable SET open_graph_image_meta = REPLACE(open_graph_image_meta, %s, %s)", '//var/', '/var/' );
        $result = $wpdb->query( $query );
        $html .= '<p>9b. Tabulka: <b>yoast_indexable</b>, sloupec <b>open_graph_image_meta</b>, špatné adresy se dvěma lomítkama, '.$result.' záznamů bylo aktualizováno.</p>';
    }

    return $html;
}

function sits3_get_s3_settings():array {

    $settings = [];

    if ( sits3_check_is_s3() === true ) {
        // S3
        $wpmf_settings = unserialize( WPMF_AWS3_SETTINGS );

        $bucket = $wpmf_settings["bucket"];
        $region = $wpmf_settings["region"];
        $root_folder_name = $wpmf_settings["root_folder_name"];

        $old_bucket = get_option( "sits3_old_bucket" );

        $settings["bucket"] = $bucket;
        $settings["region"] = $region;
        $settings["root_folder_name"] = $root_folder_name;
        $settings["s3_url"] = "https://s3.". $region .".amazonaws.com/". $bucket ."/". $root_folder_name ."/var/www/rocketstack/web/app/uploads/";
        $settings["sits3_old_bucket"] = $old_bucket;
    }

    return $settings;
}

function sits3_check_is_s3():bool {
    return ( defined( 'WPMF_AWS3_SETTINGS' ) );
}

function sits3_get_run_url():string {
    return wp_nonce_url( "?page=sit-update-bucket-settings&action=sits3", "sits3" );
}
