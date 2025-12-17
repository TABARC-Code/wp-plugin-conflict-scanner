<?php
/**
 * Plugin Name: WP Plugin Conflict Scanner
 * Plugin URI: https://github.com/TABARC-Code/wp-plugin-conflict-scanner
 * Description: Tries to show which plugins are fighting over hooks and shortcodes, so I do not have to play "disable everything" for three hours.
 * Version: 1.0.0
 * Author: TABARC-Code
 * Author URI: https://github.com/TABARC-Code
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright (c) 2025 TABARC-Code
 * Original work by TABARC-Code.
 * You may modify and redistribute this software under the terms of
 * the GNU General Public License version 3 or (at your option) any later version.
 * Preserve this notice and be honest about your changes.
 *
 * Why this exists:
 * Plugin conflicts are the worst kind of guessing game. Two or more plugins
 * hook into the same thing, override each other, or load overlapping scripts.
 * The usual debug pattern is "disable everything and slowly turn things back on".
 * I would rather have a screen that says "here are the hooks where multiple plugins
 * pile in, and here are the shortcodes that look suspicious".
 *
 * This will not magically find every conflict. It just gives me a starting map.
 *
 * TODO: add a way to log actual fatal errors and tie them to plugin files.
 * TODO: surface suspicious script and style handles by origin plugin.
 * FIXME: scanning all hooks on huge sites could get heavy; right now I focus on sensitive hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_Plugin_Conflict_Scanner' ) ) {

    class WP_Plugin_Conflict_Scanner {

        private $screen_slug = 'wp-plugin-conflict-scanner';

        /**
         * Hooks that tend to cause the most visible conflicts.
         *
         * I am not listing every hook in existence. That would be unreadable.
         */
        private $focus_hooks = array(
            'the_content',
            'the_excerpt',
            'wp_head',
            'wp_footer',
            'init',
            'template_redirect',
            'wp_enqueue_scripts',
            'admin_enqueue_scripts',
            'plugins_loaded',
            'widgets_init',
        );

        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
            add_action( 'admin_head-plugins.php', array( $this, 'inject_plugin_list_icon_css' ) );
        }

        /**
         * Central place for the SVG icon I keep reusing across projects.
         */
        private function get_brand_icon_url() {
            return plugin_dir_url( __FILE__ ) . '.branding/tabarc-icon.svg';
        }

        public function add_tools_page() {
            add_management_page(
                __( 'Plugin Conflict Scanner', 'wp-plugin-conflict-scanner' ),
                __( 'Conflict Scanner', 'wp-plugin-conflict-scanner' ),
                'manage_options',
                $this->screen_slug,
                array( $this, 'render_tools_page' )
            );
        }

        public function render_tools_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-plugin-conflict-scanner' ) );
            }

            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            $plugin_map   = $this->build_plugin_file_map();
            $hook_report  = $this->scan_hooks( $plugin_map );
            $shortcodes   = $this->scan_shortcodes( $plugin_map );

            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Plugin Conflict Scanner', 'wp-plugin-conflict-scanner' ); ?></h1>
                <p>
                    This is my "what is fighting with what" map. It does not prove a conflict,
                    but it does show where multiple plugins are hooked into the same critical spots.
                    Better than blindly disabling everything while swearing at the screen.
                </p>

                <h2><?php esc_html_e( 'Hotspot hooks', 'wp-plugin-conflict-scanner' ); ?></h2>
                <p>
                    These are the hooks I worry about first. If several plugins are piled onto
                    <code>the_content</code>, <code>wp_head</code> or <code>template_redirect</code>,
                    I pay attention.
                </p>
                <?php $this->render_hook_table( $hook_report ); ?>

                <h2><?php esc_html_e( 'Shortcodes with multiple owners', 'wp-plugin-conflict-scanner' ); ?></h2>
                <p>
                    If more than one plugin registers or touches the same shortcode, it becomes a
                    lovely source of mystery bugs and random output. This table shows what my best
                    guess is for who owns which shortcode callbacks.
                </p>
                <?php $this->render_shortcode_table( $shortcodes ); ?>

                <h2><?php esc_html_e( 'How to use this', 'wp-plugin-conflict-scanner' ); ?></h2>
                <p>
                    This is not a verdict. It is a list of suspects. If the site breaks when you
                    view a page that uses shortcode <code>[example]</code> and this screen shows
                    three plugins hooking into it, you know where to start toggling things.
                </p>
            </div>
            <?php
        }

        /**
         * Build a map from file path to plugin name and slug.
         *
         * I lean on get_plugins() and then figure out which plugin directory
         * owns which file path. Longest path match wins.
         */
        private function build_plugin_file_map() {
            $all_plugins = get_plugins();
            $map         = array();

            foreach ( $all_plugins as $plugin_file => $data ) {
                $plugin_path = trailingslashit( WP_PLUGIN_DIR ) . $plugin_file;
                $plugin_dir  = trailingslashit( dirname( $plugin_path ) );

                $map[] = array(
                    'plugin_file' => $plugin_file,
                    'dir'         => $plugin_dir,
                    'name'        => isset( $data['Name'] ) ? $data['Name'] : $plugin_file,
                    'slug'        => dirname( $plugin_file ),
                );
            }

            // Sort by dir length descending so the deepest match wins.
            usort(
                $map,
                function ( $a, $b ) {
                    return strlen( $b['dir'] ) <=> strlen( $a['dir'] );
                }
            );

            return $map;
        }

        /**
         * Try to resolve a file path to a plugin record from our map.
         */
        private function resolve_file_to_plugin( $file, $plugin_map ) {
            if ( empty( $file ) ) {
                return null;
            }

            $file = wp_normalize_path( $file );

            foreach ( $plugin_map as $plugin ) {
                $dir = wp_normalize_path( $plugin['dir'] );
                if ( strpos( $file, $dir ) === 0 ) {
                    return $plugin;
                }
            }

            return null;
        }

        /**
         * Scan selected hooks and see which plugins attach callbacks to them.
         *
         * I focus on a curated list of hooks that tend to be conflict magnets.
         */
        private function scan_hooks( $plugin_map ) {
            global $wp_filter;

            $report = array();

            foreach ( $this->focus_hooks as $hook_name ) {
                if ( ! isset( $wp_filter[ $hook_name ] ) ) {
                    continue;
                }

                $callbacks   = $this->flatten_hook_callbacks( $wp_filter[ $hook_name ] );
                $plugin_hits = array();

                foreach ( $callbacks as $cb ) {
                    $file = $this->get_callback_file( $cb['callback'] );
                    $plugin = $this->resolve_file_to_plugin( $file, $plugin_map );

                    if ( $plugin ) {
                        $key = $plugin['plugin_file'];
                        if ( ! isset( $plugin_hits[ $key ] ) ) {
                            $plugin_hits[ $key ] = array(
                                'plugin'   => $plugin,
                                'callbacks'=> array(),
                            );
                        }
                        $plugin_hits[ $key ]['callbacks'][] = $cb;
                    }
                }

                if ( count( $plugin_hits ) > 1 ) {
                    $report[ $hook_name ] = $plugin_hits;
                }
            }

            return $report;
        }

        /**
         * Flatten a WP_Hook object into a list of callbacks with priority.
         */
        private function flatten_hook_callbacks( $hook ) {
            $result = array();

            if ( is_a( $hook, 'WP_Hook' ) && is_array( $hook->callbacks ) ) {
                foreach ( $hook->callbacks as $priority => $group ) {
                    foreach ( $group as $cb ) {
                        $result[] = array(
                            'priority' => $priority,
                            'callback' => $cb['function'],
                        );
                    }
                }
            } elseif ( is_array( $hook ) ) {
                // Older or odd cases.
                foreach ( $hook as $priority => $group ) {
                    foreach ( $group as $cb ) {
                        $result[] = array(
                            'priority' => $priority,
                            'callback' => $cb['function'],
                        );
                    }
                }
            }

            return $result;
        }

        /**
         * Try to get the file that defines a callback.
         *
         * This is where I play "ask Reflection nicely" and hope the callback is not
         * something unspeakable created at runtime.
         */
        private function get_callback_file( $callback ) {
            try {
                if ( is_string( $callback ) && function_exists( $callback ) ) {
                    $ref = new ReflectionFunction( $callback );
                    return $ref->getFileName();
                }

                if ( is_array( $callback ) && count( $callback ) >= 2 ) {
                    $object_or_class = $callback[0];
                    $method          = $callback[1];

                    if ( is_object( $object_or_class ) ) {
                        $ref = new ReflectionMethod( $object_or_class, $method );
                        return $ref->getFileName();
                    }

                    if ( is_string( $object_or_class ) && class_exists( $object_or_class ) ) {
                        $ref = new ReflectionMethod( $object_or_class, $method );
                        return $ref->getFileName();
                    }
                }

                if ( $callback instanceof Closure ) {
                    $ref = new ReflectionFunction( $callback );
                    return $ref->getFileName();
                }
            } catch ( ReflectionException $e ) {
                // I am not logging this right now. This is best effort only.
                return null;
            }

            return null;
        }

        /**
         * Scan shortcodes and see which plugins register each one.
         */
        private function scan_shortcodes( $plugin_map ) {
            global $shortcode_tags;

            if ( ! is_array( $shortcode_tags ) || empty( $shortcode_tags ) ) {
                return array();
            }

            $report = array();

            foreach ( $shortcode_tags as $tag => $callback ) {
                $file   = $this->get_callback_file( $callback );
                $plugin = $this->resolve_file_to_plugin( $file, $plugin_map );

                if ( ! $plugin ) {
                    continue;
                }

                if ( ! isset( $report[ $tag ] ) ) {
                    $report[ $tag ] = array();
                }

                $key = $plugin['plugin_file'];
                if ( ! isset( $report[ $tag ][ $key ] ) ) {
                    $report[ $tag ][ $key ] = array(
                        'plugin'   => $plugin,
                        'callbacks'=> array(),
                    );
                }

                $report[ $tag ][ $key ]['callbacks'][] = array(
                    'callback' => $callback,
                );
            }

            // Keep only tags with more than one plugin registered.
            foreach ( $report as $tag => $plugins ) {
                if ( count( $plugins ) < 2 ) {
                    unset( $report[ $tag ] );
                }
            }

            return $report;
        }

        private function render_hook_table( $hook_report ) {
            if ( empty( $hook_report ) ) {
                echo '<p>' . esc_html__( 'No obvious hotspots found for the tracked hooks. Does not mean there is no conflict, just that nothing stands out.', 'wp-plugin-conflict-scanner' ) . '</p>';
                return;
            }

            ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Hook', 'wp-plugin-conflict-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Plugins attached', 'wp-plugin-conflict-scanner' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $hook_report as $hook_name => $plugins ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $hook_name ); ?></code></td>
                        <td>
                            <?php foreach ( $plugins as $plugin_file => $data ) : ?>
                                <div style="margin-bottom:6px;">
                                    <strong><?php echo esc_html( $data['plugin']['name'] ); ?></strong>
                                    <span style="opacity:0.7;">(<?php echo esc_html( $data['plugin']['plugin_file'] ); ?>)</span>
                                    <br>
                                    <span style="font-size:12px;">
                                        <?php
                                        $priorities = array_unique(
                                            array_map(
                                                function ( $cb ) {
                                                    return (int) $cb['priority'];
                                                },
                                                $data['callbacks']
                                            )
                                        );
                                        sort( $priorities );
                                        echo esc_html__( 'Priorities:', 'wp-plugin-conflict-scanner' ) . ' ' . esc_html( implode( ', ', $priorities ) );
                                        ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        }

        private function render_shortcode_table( $shortcodes ) {
            if ( empty( $shortcodes ) ) {
                echo '<p>' . esc_html__( 'No shortcodes with multiple plugin origins were detected. Good. Or you just have not loaded the parts of the site that register them yet.', 'wp-plugin-conflict-scanner' ) . '</p>';
                return;
            }

            ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Shortcode', 'wp-plugin-conflict-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Plugins involved', 'wp-plugin-conflict-scanner' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $shortcodes as $tag => $plugins ) : ?>
                    <tr>
                        <td><code>[<?php echo esc_html( $tag ); ?>]</code></td>
                        <td>
                            <?php foreach ( $plugins as $plugin_file => $data ) : ?>
                                <div style="margin-bottom:6px;">
                                    <strong><?php echo esc_html( $data['plugin']['name'] ); ?></strong>
                                    <span style="opacity:0.7;">(<?php echo esc_html( $data['plugin']['plugin_file'] ); ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        }

        /**
         * Tiny branding touch in the plugin list.
         */
        public function inject_plugin_list_icon_css() {
            $icon_url = esc_url( $this->get_brand_icon_url() );
            ?>
            <style>
                .wp-list-table.plugins tr[data-slug="wp-plugin-conflict-scanner"] .plugin-title strong::before {
                    content: '';
                    display: inline-block;
                    vertical-align: middle;
                    width: 18px;
                    height: 18px;
                    margin-right: 6px;
                    background-image: url('<?php echo $icon_url; ?>');
                    background-repeat: no-repeat;
                    background-size: contain;
                }
            </style>
            <?php
        }
    }

    new WP_Plugin_Conflict_Scanner();
}
