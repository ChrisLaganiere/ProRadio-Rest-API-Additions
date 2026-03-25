<?php
/**
 * Plugin Name: ProRadio Custom REST API Additions
 * Description: Expose ProRadio Songs (proradio-songs) and schedule of shows to the WP REST API with custom fields.
 * Version: 1.17
 */

// 1) Force-enable REST for songs (late, after plugin registers it)
add_action('init', function () {
    $pt = 'proradio-songs';

    if (post_type_exists($pt)) {
        global $wp_post_types;
        if (isset($wp_post_types[$pt])) {
            $wp_post_types[$pt]->show_in_rest = true;
            $wp_post_types[$pt]->rest_base = 'songs';
            $wp_post_types[$pt]->rest_controller_class = 'WP_REST_Posts_Controller';
        }
    }
}, 999);

// 2) Register custom REST fields at REST init to expose artist, title separately
add_action('rest_api_init', function () {
    $pt = 'proradio-songs';

    // If the post type still isn't registered, don't register fields
    if (!post_type_exists($pt)) return;

    register_rest_field($pt, 'song_title', [
        'get_callback' => function ($post_arr) {
            $id = $post_arr['id'];

            $v = get_post_meta($id, 'prsidekick_song', true);

            return (string) $v;
        },
        'schema' => [
            'description' => 'Song title',
            'type' => 'string',
            'context' => ['view', 'edit'],
        ],
    ]);

    register_rest_field($pt, 'artist', [
        'get_callback' => function ($post_arr) {
            $id = $post_arr['id'];

            $v = get_post_meta($id, 'prsidekick_artist', true);

            return (string) $v;
        },
        'schema' => [
            'description' => 'Song artist',
            'type' => 'string',
            'context' => ['view', 'edit'],
        ],
    ]);
});

// 3) Expose 'shows_list' field on schedule api endpoint: `/wp-json/wp/v2/schedule`
add_action( 'rest_api_init', 'register_psyched_schedule_rest_field' );

function register_psyched_schedule_rest_field() {
    register_rest_field( 'schedule', 
        'shows_list', // Changed this to match what you're looking for
        array(
            'get_callback'    => 'get_psyched_schedule_meta',
            'update_callback' => null,
            'schema'          => null,
        )
    );
}

function get_psyched_schedule_meta( $object ) {
    $post_id = $object['id'];
    
    // 1. Fetch the meta
    $raw_meta = get_post_meta( $post_id, 'track_repeatable', true );

    // 2. Force unserialize if WP didn't do it automatically
    $data = maybe_unserialize( $raw_meta );

    if ( empty( $data ) || ! is_array( $data ) ) {
        return [];
    }

    $formatted = [];

    foreach ( $data as $item ) {
        // Flatten the show_id (it's stored as an array in your dump)
        $show_id = ( isset($item['show_id']) && is_array($item['show_id']) ) 
                   ? $item['show_id'][0] 
                   : null;

        $formatted[] = [
            'show_id'    => $show_id,
            'start_time' => isset($item['show_time']) ? $item['show_time'] : '',
            'end_time'   => isset($item['show_time_end']) ? $item['show_time_end'] : '',
        ];
    }

    return $formatted;
}


// 4) debugging
// add_action('wp_dashboard_setup', 'my_debug_dashboard_widget');

// function my_debug_dashboard_widget() {
//     wp_add_dashboard_widget(
//         'my_custom_debug_widget',         // Widget slug
//         '🛠️ Dev Debug Console',          // Title
//         'render_my_debug_widget'          // Display callback
//     );
// }

// function render_my_debug_widget() {
//     // Example: Debugging the schedule data for a specific post
//     $test_id = 1836; 
//     $raw_meta = get_post_meta($test_id, 'track_repeatable', true );

//     // 2. Force unserialize if WP didn't do it automatically
//     $data = maybe_unserialize( $raw_meta );

//     if ( empty( $data ) || ! is_array( $data ) ) {
//         echo '<p><strong>Failed to extract Post ID:</strong> ' . $test_id . '</p>';
//         return;
//     }

//     echo '<p><strong>Debugging Post ID:</strong> ' . $test_id . '</p>';
//     echo '<pre style="background: #f0f0f0; padding: 10px; overflow: auto; max-height: 300px;">';

//     echo '<ul>';
//     foreach ( $data as $item ) {
//         echo '<li>';
        
//         $show_id = ( isset($item['show_id']) && is_array($item['show_id']) ) 
//                    ? $item['show_id'][0] 
//                    : null;
//         $start_time = isset($item['show_time']) ? $item['show_time'] : '';
//         $end_time   = isset($item['show_time_end']) ? $item['show_time_end'] : '';
//         print_r($show_id);
//         echo ', ';
//         print_r($start_time);
//         echo ', ';
//         print_r($end_time);
//         echo '</li>';
//     }
//     echo '</ul>';

//     echo '</pre>';
// }
