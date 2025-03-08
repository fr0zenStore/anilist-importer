<?php
/**
 * Plugin Name: Anilist Importer
 * Description: Imports anime data from the AniList API and saves it in custom fields.
 * Version: 1.2
 * Author: fr0zen
 * Requires PHP: 8.2
 */

if (!defined('ABSPATH')) exit; // Prevent direct access

class AnilistImporter {
    
    public function __construct() {
        add_action('init', [$this, 'register_anime_cpt']);
        add_action('add_meta_boxes', [$this, 'add_anime_metabox']);
        add_action('save_post_anime', [$this, 'save_anime_data']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_fetch_anilist_data', [$this, 'fetch_anilist_data']);
    }

    // Register Anime CPT
    public function register_anime_cpt(): void {
        register_post_type('anime', [
            'labels' => ['name' => 'Anime', 'singular_name' => 'Anime'],
            'public' => true,
            'menu_icon' => 'dashicons-video-alt2',
            'supports' => ['title', 'editor', 'thumbnail'],
            'taxonomies' => ['studio', 'year', 'season'],
        ]);

        register_taxonomy('studio', 'anime', ['label' => 'Studio', 'hierarchical' => false]);
        register_taxonomy('year', 'anime', ['label' => 'Year', 'hierarchical' => false]);
        register_taxonomy('season', 'anime', ['label' => 'Season', 'hierarchical' => false]);
    }

    // Add Meta Box
    public function add_anime_metabox(): void {
        add_meta_box('anime_details', 'Anime Details', [$this, 'anime_metabox_html'], 'anime', 'normal', 'high');
    }

    // Meta Box HTML
    public function anime_metabox_html(\WP_Post $post): void {
        $anime_data = get_post_meta($post->ID, '_anime_data', true) ?: [];
        ?>
        <label for="anime_title">Anime Title:</label>
        <input type="text" id="anime_title" name="anime_title" value="<?php echo esc_attr(get_the_title($post->ID)); ?>" style="width:100%;">
        <button id="fetch_anilist_btn" class="button button-primary">Fetch from Anilist</button>
        <br><br>
        <label>Episodes:</label>
        <input type="number" id="anime_episodes" name="anime_episodes" value="<?php echo esc_attr($anime_data['episodes'] ?? ''); ?>">
        <br>
        <label>Poster URL:</label>
        <input type="text" id="anime_poster" name="anime_poster" value="<?php echo esc_url($anime_data['poster'] ?? ''); ?>" style="width:100%;">
        <br>
        <label>Background URL:</label>
        <input type="text" id="anime_background" name="anime_background" value="<?php echo esc_url($anime_data['background'] ?? ''); ?>" style="width:100%;">
        <br>
        <label>Studio:</label>
        <input type="text" id="anime_studio" name="anime_studio" value="<?php echo esc_attr($anime_data['studio'] ?? ''); ?>" style="width:100%;">
        <br>
        <label>Year:</label>
        <input type="number" id="anime_year" name="anime_year" value="<?php echo esc_attr($anime_data['year'] ?? ''); ?>">
        <br>
        <label>Season:</label>
        <input type="text" id="anime_season" name="anime_season" value="<?php echo esc_attr($anime_data['season'] ?? ''); ?>" style="width:100%;">
        <?php
    }

    // Save Anime Data
    public function save_anime_data(int $post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['anime_title'])) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $anime_data = [
            'title'      => sanitize_text_field($_POST['anime_title']),
            'episodes'   => absint($_POST['anime_episodes']),
            'poster'     => esc_url_raw($_POST['anime_poster']),
            'background' => esc_url_raw($_POST['anime_background']),
            'studio'     => sanitize_text_field($_POST['anime_studio']),
            'year'       => absint($_POST['anime_year']),
            'season'     => sanitize_text_field($_POST['anime_season'])
        ];

        update_post_meta($post_id, '_anime_data', $anime_data);
    }

    // Enqueue Scripts
    public function enqueue_admin_scripts(string $hook): void {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

        wp_enqueue_script('anilist-importer-js', plugin_dir_url(__FILE__) . 'anilist-importer.js', ['jquery'], null, true);
        wp_localize_script('anilist-importer-js', 'AnilistImporter', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    // Handle AJAX Request
    public function fetch_anilist_data(): void {
        if (!isset($_POST['title'])) {
            wp_send_json_error(['message' => 'Title is missing']);
            return;
        }

        $title = sanitize_text_field($_POST['title']);

        // AniList API Query
        $query = [
            'query' => 'query ($search: String) { 
                Media(search: $search, type: ANIME) { 
                    title { romaji } 
                    episodes 
                    coverImage { large } 
                    bannerImage 
                    studios { nodes { name } } 
                    season 
                    seasonYear 
                } 
            }',
            'variables' => ['search' => $title]
        ];

        $response = wp_remote_post('https://graphql.anilist.co', [
            'body'    => json_encode($query),
            'headers' => ['Content-Type' => 'application/json']
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API request failed']);
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $anime = $body['data']['Media'] ?? null;

        if (!$anime) {
            wp_send_json_error(['message' => 'Anime not found']);
            return;
        }

        wp_send_json_success([
            'title'      => $anime['title']['romaji'] ?? '',
            'episodes'   => $anime['episodes'] ?? 0,
            'poster'     => $anime['coverImage']['large'] ?? '',
            'background' => $anime['bannerImage'] ?? '',
            'studio'     => $anime['studios']['nodes'][0]['name'] ?? '',
            'season'     => $anime['season'] ?? '',
            'year'       => $anime['seasonYear'] ?? ''
        ]);
    }
}

new AnilistImporter();
