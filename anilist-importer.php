<?php
/**
 * Plugin Name: Anilist Importer
 * Description: Importa dati anime da AniList API e compila i campi personalizzati.
 * Version: 1.0
 * Author: fr0zen
 */

if (!defined('ABSPATH')) exit; // Protezione diretta

class AnilistImporter {
    
    public function __construct() {
        add_action('init', [$this, 'register_anime_cpt']);
        add_action('add_meta_boxes', [$this, 'add_anime_metabox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_fetch_anilist_data', [$this, 'fetch_anilist_data']);
    }

    // Registra il CPT Anime
    public function register_anime_cpt() {
        register_post_type('anime', [
            'labels' => ['name' => 'Anime', 'singular_name' => 'Anime'],
            'public' => true,
            'menu_icon' => 'dashicons-video-alt2',
            'supports' => ['title', 'editor', 'thumbnail'],
            'taxonomies' => ['studio', 'year', 'season'],
        ]);

        register_taxonomy('studio', 'anime', ['label' => 'Studio', 'hierarchical' => false]);
        register_taxonomy('year', 'anime', ['label' => 'Anno', 'hierarchical' => false]);
        register_taxonomy('season', 'anime', ['label' => 'Stagione', 'hierarchical' => false']);
    }

    // Aggiunge la meta box
    public function add_anime_metabox() {
        add_meta_box('anime_details', 'Dettagli Anime', [$this, 'anime_metabox_html'], 'anime', 'normal', 'high');
    }

    // HTML della meta box
    public function anime_metabox_html($post) {
        ?>
        <label for="anime_title">Titolo Anime:</label>
        <input type="text" id="anime_title" style="width:100%;" placeholder="Inserisci il titolo">
        <button id="fetch_anilist_btn" class="button button-primary">Recupera da Anilist</button>
        <br><br>
        <label>Episodi:</label>
        <input type="number" id="anime_episodes" name="anime_episodes">
        <br>
        <label>Poster URL:</label>
        <input type="text" id="anime_poster" name="anime_poster" style="width:100%;">
        <br>
        <label>Background URL:</label>
        <input type="text" id="anime_background" name="anime_background" style="width:100%;">
        <br>
        <label>Studio:</label>
        <input type="text" id="anime_studio" name="anime_studio" style="width:100%;">
        <br>
        <label>Anno:</label>
        <input type="number" id="anime_year" name="anime_year">
        <br>
        <label>Stagione:</label>
        <input type="text" id="anime_season" name="anime_season" style="width:100%;">
        <?php
    }

    // Carica gli script
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

        wp_enqueue_script('anilist-importer-js', plugin_dir_url(__FILE__) . 'anilist-importer.js', ['jquery'], null, true);
        wp_localize_script('anilist-importer-js', 'AnilistImporter', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    // Gestisce la richiesta AJAX
    public function fetch_anilist_data() {
        if (!isset($_POST['title'])) wp_send_json_error(['message' => 'Titolo mancante']);

        $title = sanitize_text_field($_POST['title']);

        // Query API AniList
        $query = [
            'query' => 'query ($search: String) { Media(search: $search, type: ANIME) { title { romaji } episodes coverImage { large } bannerImage studios { nodes { name } } season seasonYear } }',
            'variables' => ['search' => $title]
        ];

        $response = wp_remote_post('https://graphql.anilist.co', [
            'body'    => json_encode($query),
            'headers' => ['Content-Type' => 'application/json']
        ]);

        if (is_wp_error($response)) wp_send_json_error(['message' => 'Errore API']);

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!$body['data']['Media']) wp_send_json_error(['message' => 'Anime non trovato']);

        $anime = $body['data']['Media'];
        wp_send_json_success([
            'title'      => $anime['title']['romaji'],
            'episodes'   => $anime['episodes'],
            'poster'     => $anime['coverImage']['large'],
            'background' => $anime['bannerImage'],
            'studio'     => $anime['studios']['nodes'][0]['name'] ?? '',
            'season'     => $anime['season'],
            'year'       => $anime['seasonYear']
        ]);
    }
}

new AnilistImporter();
?>
