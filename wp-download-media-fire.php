<?php
/*---------------------------------------------------------
Plugin Name: Download Media Fire
Author: carlosramosweb
Author URI: https://criacaocriativa.com
Donate link: https://donate.criacaocriativa.com
Description: Esse plugin é uma versão BETA. Shortcode Botão Download [wp_download_media_fire], Shortcode Gif Animado [wp_gif_download_media_fire]
Text Domain: wp-download-media-fire
Domain Path: /languages/
Version: 1.0.0
Requires at least: 3.5.0
Tested up to: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html 
------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Download_Media_Fire' ) ) {   

    class Download_Media_Fire {

        public function __construct() {
            add_action( 'plugins_loaded', array( $this, 'init_functions' ) );
        }
        //=>

        public function init_functions() {
            add_action( 'init', array( $this, 'wp_register_posttype' ) );
            add_action( 'admin_menu', array( $this, 'register_download_mf_menu_page' ), 10, 2 );
            add_action( 'save_post', array( $this, 'wp_save_meta_box' ) );
            add_action( 'add_meta_boxes', array( $this, 'wp_register_meta_boxes' ) ); 
            add_action( 'wp_header', array( $this, 'wp_check_post_thumbnail_download' ) );
            add_filter( 'post_thumbnail_html', array( $this, 'wp_post_thumbnail_download' ), 99, 5 );
            add_shortcode( 'wp_download_media_fire', array( $this, 'wp_get_download_media_fire' ) );
            add_shortcode( 'wp_gif_download_media_fire', array( $this, 'wp_get_gif_download_media_fire' ) );
            add_filter( 'manage_download-mediafire_posts_columns', array( $this, 'wp_download_media_fire_columns' ) );
            add_action( 'manage_posts_custom_column', array( $this, 'wp_download_media_fire_columns_content' ), 10, 2 );
        }
        //=>

        public function register_download_mf_menu_page() {
            add_submenu_page(
                'edit.php?post_type=download-mediafire',
                'Configuração',
                'Configuração',
                'manage_options',
                'settings-download-mediafire',
                array( $this, 'download_mf_page_admin_callback' )
            );
        }
        //=>

        public function wp_check_post_thumbnail_download( $post_id, $get_download ) {
            global $post;
            $download_enabled   = false;
            $get_download       = $_GET['download'];

            if ( isset( $get_download ) && $get_download != '' ) {
                $check_download     = $this->wp_check_download_post( $post_id, $get_download );
                $download_enabled   = $check_download['download_enabled'];
                $post_thumbnail_id  = $check_download['id_download_mediafire'];
                $featured_img_url   = $check_download['featured_img_url'];

                if( $download_enabled && $featured_img_url != '' ) {                 
                    add_filter( 'post_thumbnail_html', array( $this, 'wp_post_thumbnail_download' ), 9999, 2 );
                }
            }
        }
        //=> 

        public function wp_post_thumbnail_download( $html ) {
            global $post;
            if ( isset( $_GET['download'] ) ) {
                $get_download       = $_GET['download'];
                $post_id            = get_the_ID();
                $check_download     = $this->wp_check_download_post( $post_id, $get_download );
                $featured_img_url   = $check_download['featured_img_url'];

                if ( ! empty( $featured_img_url ) ) {
                    $html = '<img src="' . $featured_img_url . '" alt="Featured Image" data-src="' . $featured_img_url . '" data-alt="" class="retina" />';
                }
            }
            return $html;
        }
        //=>

        public function wp_register_posttype() {
            $args = array(
                'public'                => false,
                'label'                 => 'Download MediaFire',
                'publicly_queryable'    => true,
                'public_queryable'      => true,
                'exclude_from_search'   => true,
                'show_ui'               => true,
                'show_in_menu'          => true,
                'show_in_nav_menus'     => false,
                'show_in_admin_bar'     => true,
                'capability_type'       => 'post',
                'query_var'             => true,
                'has_archive'           => false,
                'menu_icon'             => 'dashicons-cloud-upload',
                'supports'              => array( 'title', 'thumbnail' ), 
                'rewrite'               => false,
                // 'title', 'editor', 'comments', 'revisions', 'trackbacks', 'author', 'excerpt', 'page-attributes', 'thumbnail', 'custom-fields', and 'post-formats'
            );
            register_post_type( 'download-mediafire', $args );
        }
        //=>

        public function wp_register_meta_boxes() {
            add_meta_box( 
                'meta-box-id', 
                __( 'Configuração', 'wp-download-media-fire' ), 
                array( $this, 'wp_download_media_fire_display_callback' ),
                'download-mediafire',
                'advanced',
                'high'
            );
        }
        //=>
        
        public function wp_download_media_fire_columns( $column ) {
            $user                = wp_get_current_user();
            $allowed_roles       = array('administrator');
            if( array_intersect( $allowed_roles, $user->roles ) ) {
                $column['visits_dmf'] = 'Visitas';
            }
            return $column;
        }
        //=>

        public function wp_download_media_fire_columns_content( $column_id, $post_id ) {
            $user                = wp_get_current_user();
            $allowed_roles       = array('administrator');
            if( array_intersect( $allowed_roles, $user->roles ) ) {
                switch( $column_id ) { 
                    case 'visits_dmf':
                        echo ( $value = get_post_meta( $post_id, '_count_download_mediafire', true ) ) ? $value : '0';
                    break;
               }
            }
        }
        //=>

        public function wp_download_media_fire_display_callback( $post ) { 
            global $wpdb;
            $user                = wp_get_current_user();
            $allowed_roles       = array('administrator');

            $settings = array();
            $settings = get_option( 'wp_download_mediafire_settings' );
             // Mudar aqui o ID do Post Fixo
            $post_fixed_standard  = $settings['post_fixed_download_media_fire']; // 1427

            $gif_checked = '';
            $gif = get_post_meta( get_the_ID(), '_gif_download_mediafire', true );
            if ( ! empty( $gif ) ) {
                $gif_checked = 'checked="checked"';
            }
            $link = get_post_meta( get_the_ID(), '_link_download_mediafire', true );
            if ( empty( $link ) ) {
                $link = "";
            }
            $generated_download = get_post_meta( get_the_ID(), '_generated_download_mediafire', true );
            if ( empty( $generated_download ) ) {
               $generated_download = "";
            }
            $post_fixed      = get_post_meta( get_the_ID(), '_post_fixed_download_mediafire', true );
            $post_fixed      = $post_fixed_standard;
            $post_standard   = get_permalink( $post_fixed );
            if ( ! empty( $post_standard ) ) {
               $link_post_standard = esc_url( $post_standard . '?download=' . $generated_download . '&id=' . get_the_ID() );
            }  
            $seconds = get_post_meta( get_the_ID(), '_seconds_download_mediafire', true );
            if ( empty( $seconds ) ) {
                $seconds = '35';
            }
            ?>
            <?php if( array_intersect( $allowed_roles, $user->roles ) ) { ?>
            <p class="form-field _gif_field">
                <label for="_gif_download_mediafire">Gif Animado:</label>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span>Gif Animado:</span>
                    </legend>
                    <label for="_gif_download_mediafire">
                    <input name="_gif_download_mediafire" type="checkbox" id="_gif_download_mediafire" <?php echo $gif_checked; ?> value="1">
                    Habilitar/Desabilitar
                    </label>
                </fieldset>
            </p>
            <?php } else { ?>
            <input type="hidden" name="_gif_download_mediafire" value="<?php echo $gif; ?>" required>
            <?php } ?>
            <p class="form-field _link_field">
                <label for="_link_download_mediafire">Link Download:</label><br/>
                <input type="text" class="wp_input_link" name="_link_download_mediafire" value="<?php echo $link; ?>" placeholder="https://" required>
            </p>
            <?php if( array_intersect( $allowed_roles, $user->roles ) ) { ?>
            <input type="hidden" name="_post_fixed_download_mediafire" value="<?php echo $post_fixed; ?>" required>
            <p class="form-field _seconds_field">
                <label for="_seconds_download_mediafire">Segundos para iniciar o Download:</label><br/>
                <input type="number" class="wp_input_number" name="_seconds_download_mediafire" style="width: 50%; min-width: 100px;" min="10" step="1" max="50" value="<?php echo $seconds; ?>" placeholder="Mínimo de 10s e Máximo de 50s" required>
            </p>
            <?php } else { ?>
            <input type="hidden" name="_post_fixed_download_mediafire" value="<?php echo $post_fixed; ?>" required>
            <?php } ?>
            <?php if ( $post_fixed != '' && $generated_download != '' ) { ?>
            <p class="form-field _post_fixed_field">
                <label for="_post_fixed_download_mediafire">
                    <strong>Post Fixo para Download:</strong>
                </label><br/>
                <a href="<?php echo $link_post_standard;?>" target="_blank">
                    <?php echo $link_post_standard;?>
                </a><br/><br/>
                <i>Obs: Copie e Cole.</i>
            </p>
            <?php } ?>
            <br/>
            <?php
        }
        //=>

        public function wp_save_meta_box( $post_id ) {
            if ( isset( $_POST ) ) {
                if ( isset( $_POST['post_type'] ) && $_POST['post_type'] == "download-mediafire" ) {
                    global $wpdb;
                    $user = wp_get_current_user();
                    $allowed_roles = array('administrator');

                    $settings = array();
                    $settings = get_option( 'wp_download_mediafire_settings' );

                    $gif_download_mediafire = '';
                    if ( $settings['enabled_gif_download_media_fire'] == 'yes' ) {
                        $gif_download_mediafire = 1;
                    } else {
                        $gif_download_mediafire = '';
                    }
                    if ( $_POST['_gif_download_mediafire'] != '' && array_intersect( $allowed_roles, $user->roles ) ) {
                        $gif_download_mediafire = 1;
                    }

                    if ( array_key_exists( '_gif_download_mediafire', $_POST ) ) {
                        update_post_meta(
                            $post_id,
                            '_gif_download_mediafire',
                            $gif_download_mediafire
                        );
                    } else {
                        update_post_meta(
                            $post_id,
                            '_gif_download_mediafire',
                            $gif_download_mediafire
                        );
                    }
                    if ( array_key_exists( '_link_download_mediafire', $_POST ) ) {
                        update_post_meta(
                            $post_id,
                            '_link_download_mediafire',
                            $_POST['_link_download_mediafire']
                        );

                        $post_fixed  = $_POST['_post_fixed_download_mediafire'];
                        $generated   = md5( $post_fixed . $user->ID . $_POST['_link_download_mediafire'] );

                        update_post_meta(
                            $post_id,
                            '_generated_download_mediafire',
                            $generated
                        );

                        update_post_meta(
                            $post_id,
                            '_post_fixed_download_mediafire',
                            $post_fixed
                        );
                    } else {
                        update_post_meta(
                            $post_id,
                            '_link_download_mediafire',
                            ''
                        );
                    }
                    if ( array_key_exists( '_seconds_download_mediafire', $_POST ) ) {
                        $seconds = $_POST['_seconds_download_mediafire'];
                        if ( $seconds < 10 ) {
                            $seconds = 10;
                        } else if ( $seconds > 50 ) {
                            $seconds = 50;
                        }
                        update_post_meta(
                            $post_id,
                            '_seconds_download_mediafire',
                           $seconds
                        );
                    } else {
                        if ( ! array_intersect( $allowed_roles, $user->roles ) ) {
                            $seconds = 35;
                        }
                        update_post_meta(
                            $post_id,
                            '_seconds_download_mediafire',
                            $seconds
                        );
                    }
                }
            }
        }
        //=>

        public function wp_check_download_post( $post_id, $get_download ) {
            global $post;
            $download_enabled = false;

            $args = array(
              'posts_per_page' => -1,
              'post_type'      => 'download-mediafire'
            ); 
            $download_list = get_posts( $args );

            foreach ( $download_list as $download ) {
                $download_id    = $download->ID;
                $post_fixed     = get_post_meta( $download_id, '_post_fixed_download_mediafire', true );
                $link_download  = get_post_meta( $download_id, '_link_download_mediafire', true );
                if ( $post_fixed == $post_id ) {
                    $get_users = get_users();
                    foreach ( $get_users as $user ) {
                        $generated = md5( $post_id . $user->ID . $link_download );
                        if ( $generated == $get_download ) {
                            $download_enabled                        = true;
                            $args_download['download_enabled']       = true;
                            $args_download['id_download_mediafire']  = $post_id;
                            $args_download['id_download']            = $download_id;
                            $args_download['gif_download_mediafire'] = get_post_meta( $download_id, '_gif_download_mediafire', true );
                            $args_download['generated_mediafire']    = get_post_meta( $download_id, '_generated_download_mediafire', true );
                            $args_download['post_fixed_mediafire']   = get_post_meta( $download_id, '_post_fixed_download_mediafire', true );
                            $args_download['link_mediafire']         = get_post_meta( $download_id, '_link_download_mediafire', true );
                            $args_download['seconds_mediafire']      = get_post_meta( $download_id, '_seconds_download_mediafire', true );
                            $args_download['featured_img_url']       = get_the_post_thumbnail_url( $download_id, 'full' ); 
                            
                        }
                    }
                } 
            }
            if( $download_enabled ) {
                return $args_download;
            } else {
                return false;
            }
        }
        //=>
        
        public function wp_check_count_download( $download_id ) {
            $count_download = get_post_meta( $download_id, '_count_download_mediafire', true );
            if ( ! empty( $count_download ) ) {
                $count_download = ( intval( $count_download ) + 1 );
                update_post_meta(
                    $download_id,
                    '_count_download_mediafire',
                    $count_download
                );
            } else {
                update_post_meta(
                    $download_id,
                    '_count_download_mediafire',
                    '1'
                );
            }
            return "<p>count: " . $count_download . "</p>";
        }
        //=>

        public function wp_get_gif_download_media_fire( $atts ) { 
            global $post;
            $download_enabled = false;

            $response = "<!----shortcode [gif_download_media_fire]---->";

            if ( isset( $_GET['download'] ) && $_GET['download'] != '' ) {
                $post_id      = get_the_ID();
                $get_download = $_GET['download'];

                $check_download     = $this->wp_check_download_post( $post_id, $get_download );
                $download_enabled   = $check_download['download_enabled'];
                $gif_download       = $check_download['gif_download_mediafire'];

                if( $download_enabled && $gif_download == 1 ) {
                    $gif  = "<p style='gif-download-mediafire'>";
                    $gif .= "<img src='" . plugins_url( 'wp-download-media-fire/images/download-tecnologia.gif' ) . "'>";
                    $gif .= "</p>";

                    $response .= $gif;
                }
            }
            return $response;
        }
        //=>

        public function wp_get_download_media_fire( $atts ) { 
            global $post;
            $download_enabled = false;
            $response = "<!----shortcode [download_media_fire]---->";

            if ( isset( $_GET['download'] ) ) {
                $post_id      = get_the_ID();
                $get_download = $_GET['download'];

                $check_download     = $this->wp_check_download_post( $post_id, $get_download );
                $download_enabled   = $check_download['download_enabled'];

                if( $download_enabled ) {
                    $download_id            = $check_download['id_download'];
                    $gif_download_mediafire = $check_download['gif_download_mediafire'];
                    $generated_mediafire    = $check_download['generated_mediafire'];
                    $post_fixed_mediafire   = $check_download['post_fixed_mediafire'];
                    $link_mediafire         = $check_download['link_mediafire'];
                    $seconds_mediafire      = $check_download['seconds_mediafire'];
                }
            }

            if( $download_enabled ) {
                $this->wp_check_count_download( $download_id );

                $countdown_download = '<div id="countdown" style="display:block; margin:0 auto; text-align:center;">Aguarde...</div>';
                $link_download = '<a id="download_link" style="display: none; margin:0 auto; text-align:center; font-family: Verdana; font-size: 20px; color: red;" 
                href="' . $link_mediafire . '">
                    <b>DOWNLOAD</b>
                </a>';
                $style_download = "style='font-family:Verdana; font-size:20px; color:red;'";
                $script_download = '<noscript>ATIVE O JAVASCRIPT PARA BAIXAR</noscript>
                <script type="application/javascript">
                (function(){
                   var message           = "%d SEGUNDOS ANTES DO DOWNLOAD APARECER";
                   var count             = ' . $seconds_mediafire . ';
                   var download_link     = document.getElementById( "download_link" );
                   var countdown_element = document.getElementById( "countdown" );
                   var timer  = setInterval( function(){
                      if ( count ) {
                        var cd_element  = "<b ' . $style_download . '>AGUARDE %d SEGUNDOS<br>PARA BAIXAR.</b>";
                        cd_element      = cd_element.replace( "%d", count );
                        countdown_element.innerHTML = cd_element;
                        count--;
                      } else {
                        clearInterval( timer );
                        countdown_element.style.display = "none";
                        download_link.style.display = "block";
                      }
                   }, 1100 );
                } )();
                </script>';

                $download_enabled = "<!----[download_enabled]---->";

                $response .= $link_download . $countdown_download . $script_download;
            }

            return $response . $download_enabled;
        }
        //=>

        public function download_mf_page_admin_callback() {
            global $wpdb;
            $message = "";
            $settings = array();
            $settings = get_option( 'wp_download_mediafire_settings' );

            if( isset( $_POST['_update'] ) && isset( $_POST['_wpnonce'] ) ) {
                $_update = sanitize_text_field( $_POST['_update'] );
                $_wpnonce = sanitize_text_field( $_POST['_wpnonce'] );

                if( isset( $_wpnonce ) && isset( $_update ) ) {
                    if ( ! wp_verify_nonce( $_wpnonce, "settings-download-mediafire" ) ) {
                        $message = "error"; 
                    } else {
                        $new_settings = array();
                        $new_settings['enabled_gif_download_media_fire'] = isset( $_POST['enabled_gif_download_media_fire'] ) ? sanitize_text_field( $_POST['enabled_gif_download_media_fire'] ) : 'no';

                        $new_settings['post_fixed_download_media_fire'] = isset( $_POST['post_fixed_download_media_fire'] ) ? sanitize_text_field( $_POST['post_fixed_download_media_fire'] ) : '10';

                        if ( isset( $_POST['enabled_gif_download_media_fire'] ) ) {
                           $gif_download_media_fire = '1';
                        } else {
                            $gif_download_media_fire = '';
                        }
                        $args = array(
                          'posts_per_page' => -1,
                          'post_type'      => 'download-mediafire'
                        ); 
                        $download_list = get_posts( $args );

                        foreach ( $download_list as $download ) {
                            $download_id    = $download->ID;
                            update_post_meta(
                                $download_id,
                                '_gif_download_mediafire',
                                $gif_download_media_fire
                            );
                        }

                        update_option( 'wp_download_mediafire_settings', $new_settings );
                        $message = "updated";
                    }
                }
            }

            $settings = get_option( 'wp_download_mediafire_settings' );
            $enabled_gif_download_media_fire = $settings['enabled_gif_download_media_fire'];
            $post_fixed_download_media_fire  = $settings['post_fixed_download_media_fire'];
            ?>
            <div id="wpwrap">

                <h1>Configuração</h1>
                <p>Defina algumas configurações desse plugin.<p/> 

                <?php if( isset( $message ) ) { ?>
                    <div class="wrap">    
                        <?php if( $message == "updated" ) { ?>
                        <div id="message" class="updated notice is-dismissible" style="margin-left: 0px;">
                            <p>Sucesso! Os dados foram atualizados com sucesso!</p>
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text">
                                    Ignore este aviso.
                                </span>
                            </button>
                        </div>
                        <?php } ?>    
                        <?php if( $message == "error" ) { ?>
                        <div id="message" class="updated error is-dismissible" style="margin-left: 0px;">
                            <p>Erro! Não foi possível fazer as atualizações!</p>
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text">
                                    Ignore este aviso.
                                </span>
                            </button>
                        </div>
                        <?php } ?>
                    </div>
                <?php } ?>
                
                <div class="wrap woocommerce">

                    <nav class="nav-tab-wrapper wc-nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=settings-download-mediafire' ) ); ?>" class="nav-tab nav-tab-active">
                            Configuração
                        </a>
                    </nav>
                    <!--form-->
                    <form method="POST" id="mainform" name="mainform">
                        <!---->
                        <table class="form-table">
                            <tbody>
                                <!---->
                                <tr valign="top">
                                    <th scope="row">
                                        <label>
                                           Habilitar Gif Animado:
                                        </label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enabled_gif_download_media_fire" value="yes" <?php if( esc_attr( $enabled_gif_download_media_fire ) == "yes" ) { echo 'checked="checked"'; } ?>>
                                            <span>Sim!</span>
                                        </label>
                                   </td>
                                </tr>  
                                <!---->
                                <tr valign="top">
                                    <th scope="row">
                                        <label>
                                           Post Fixo:
                                        </label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="number" name="post_fixed_download_media_fire" value="<?php echo $post_fixed_download_media_fire; ?>">
                                            <span>Digite um ID de Post. Ex: 1532</span>
                                        </label>
                                   </td>
                                </tr>  
                                <!----->
                           </tbody>
                        </table>
                        <!---->
                        <hr/>
                        <div class="submit">
                            <button class="button-primary" type="submit">Salvar Alterações</button>
                            <input type="hidden" name="_update" value="yes">
                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'settings-download-mediafire' ) ); ?>">
                        </div>
                        <!---->  
                    </form>
                    <!---->
                </div>
            </div>            
            <?php
        }
        //=>

    }
    //=>

    new Download_Media_Fire();
}