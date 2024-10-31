<?php
/**
 * @package Qeado Tracking
 */
/*
Plugin Name: Qeado Tracking
Plugin URI: https://qeado.com/
Description: With this plugin you can easily track traffic for Qeado.
Version: 1.0
*/

define('WGA_VERSION', '1.0');

/**
 * qeadoTracking is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 */
class qeadoTracking {

  /**
   * @var qeadoTracking - Static property to hold our singleton instance
   */
  static $instance = false;

  static $page_slug = 'wp-qeado-tracking';

  /**
   * This is our constructor, which is private to force the use of get_instance()
   * @return void
   */
  private function __construct() {
    add_filter( 'init',                     array( $this, 'init' ) );
    add_action( 'admin_init',               array( $this, 'admin_init' ) );
    add_action( 'admin_menu',               array( $this, 'admin_menu' ) );
    add_action( 'get_footer',               array( $this, 'insert_code' ) );
    add_filter( 'plugin_action_links',      array( $this, 'add_plugin_page_links' ), 10, 2 );
  }

  /**
   * Function to instantiate our class and make it a singleton
   */
  public static function get_instance() {
    if ( !self::$instance )
      self::$instance = new self;

    return self::$instance;
  }

  public function init() {
    load_plugin_textdomain( 'wp-qeado-tracking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
  }

  /**
   * This adds the options page for this plugin to the Options page
   */
  public function admin_menu() {
    add_options_page(__('Qeado Tracking', 'wp-qeado-tracking'), __('Qeado Tracking', 'wp-qeado-tracking'), 'manage_options', self::$page_slug, array( $this, 'settings_view' ) );
  }

  /**
   * Register our settings
   */
  public function admin_init() {
    register_setting( 'wqt', 'wqt', array( $this, 'sanitize_general_options' ) );

    add_settings_section( 'wqt_general', false, '__return_false', 'wqt' );
    add_settings_field( 'code', __( 'Qeado tracking ID:', 'wp-qeado-tracking' ), array( $this, 'field_code' ), 'wqt', 'wqt_general' );
    add_settings_field( 'additional_items', __( 'Additional events to track:', 'wp-qeado-tracking' ), array( $this, 'field_additional_items' ), 'wqt', 'wqt_general' );
    add_settings_field( 'do_not_track', __( 'Visits to ignore:', 'wp-qeado-tracking' ), array( $this, 'field_do_not_track' ), 'wqt', 'wqt_general' );
  }

  /**
   * Where the user adds their Qeado code
   */
  public function field_code() {
    echo '<input name="wqt[code]" id="wqt-code" type="text" value="' . esc_attr( $this->_get_options( 'code' ) ) . '" />';
    echo '<p class="description">' . __( 'Paste your Qeado tracking ID (e.g. "XXXXXX") into the field.', 'wp-qeado-tracking' ) . '</p>';
  }

  /**
   * Option to log additional items
   */
  public function field_additional_items() {
    $addtl_items = array(
      'track_social'         => __( 'Track social engagements (e.g. Facebook likes)', 'wp-qeado-tracking' ),
      'track_form'           => __( 'Track form engagements (e.g. Form submits)', 'wp-qeado-tracking' ),
      'track_downloads'      => __( 'Track downloads (e.g. PDF files)', 'wp-qeado-tracking' ),
      'track_contact_clicks' => __( 'Track contact engagements (e.g. mailto links)', 'wp-qeado-tracking' ),
    );
    foreach( $addtl_items as $id => $label ) {

      $checked = '';
      // Tracking options should always be checked by default (with new install only).
      if ( get_option( $id ) === false ) {
        // Nothing yet saved
        update_option($id, 'checked');
        $checked = ' checked';
      }

      echo '<label for="wqt_' . $id . '">';
      echo '<input id="wqt_' . $id . '"' . $checked . ' type="checkbox" name="wqt[' . $id . ']" value="true" ' . checked( 'true', $this->_get_options( $id ), false ) . ' />';
      echo '&nbsp;&nbsp;' . $label;
      echo '</label><br />';
    }

    echo '<p>' . __( 'Choose the type of events you want to track within your website. The code will automatically adjust based on your choices.' ) . '</p>';
  }

  public function field_do_not_track() {
    $do_not_track = array(
      'ignore_admin_area'       => __( 'Do not log anything in the admin area', 'wp-qeado-tracking' ),
    );
    global $wp_roles;
    foreach( $wp_roles->roles as $role => $role_info ) {
      $do_not_track['ignore_role_' . $role] = sprintf( __( 'Do not log %s when logged in', 'wp-qeado-tracking' ), rtrim( $role_info['name'], 's' ) );
    }
    foreach( $do_not_track as $id => $label ) {
      echo '<label for="wqt_' . $id . '">';
      echo '<input id="wqt_' . $id . '" type="checkbox" name="wqt[' . $id . ']" value="true" ' . checked( 'true', $this->_get_options( $id ), false ) . ' />';
      echo '&nbsp;&nbsp;' . $label;
      echo '</label><br />';
    }
  }

  /**
   * Sanitize all of the options associated with the plugin
   */
  public function sanitize_general_options( $in ) {

    $out = array();

    // The actual tracking ID
    if ( preg_match( '/([a-zA-Z0-9])+/', $in['code'], $matches ) )
      $out['code'] = $matches[0];
    else
      $out['code'] = '';

    $checkbox_items = array(
      // Additional items you can track
      'track_social',
      'track_form',
      'track_downloads',
      'track_contact_clicks',
      // Things to ignore
      'ignore_admin_area',
    );
    global $wp_roles;
    foreach( $wp_roles->roles as $role => $role_info ) {
      $checkbox_items[] = 'ignore_role_' . $role;
    }
    foreach( $checkbox_items as $checkbox_item ) {
      if ( isset( $in[$checkbox_item] ) && 'true' == $in[$checkbox_item] )
        $out[$checkbox_item] = 'true';
      else
        $out[$checkbox_item] = 'false';
    }

    return $out;
  }

  /**
   * This is used to display the options page for this plugin
   */
  public function settings_view() {
    ?>
    <div class="wrap">
      <h2><?php _e('Qeado Tracking Options', 'wp-qeado-tracking') ?></h2>
      <form action="options.php" method="post" id="wp_qeado_tracking">
        <?php
        settings_fields( 'wqt' );
        do_settings_sections( 'wqt' );
        submit_button( __( 'Update Options', 'wp-qeado-tracking' ) );
        ?>
      </form>
    </div>
  <?php
  }

  /**
   * Maybe output or return, depending on the context
   */
  private function _output_or_return( $val, $maybe ) {
    if ( $maybe )
      echo $val . "\r\n";
    else
      return $val;
  }

  /**
   * This injects the Qeado code into the footer of the page.
   *
   * @param bool[optional] $output - defaults to true, false returns but does NOT echo the code
   */
  public function insert_code( $output = true ) {
    // If $output is not a boolean false, set it to true (default).
    $output = ($output !== false);

    $tracking_id = $this->_get_options( 'code' );
    if ( empty( $tracking_id ) )
      return $this->_output_or_return( '<!-- Your Qeado Tracking Plugin is missing the tracking ID -->', $output );

    // Get our plugin options.
    $wqt = $this->_get_options();
    // If the user's role has wqt_no_track set to true, return without inserting code.
    if ( is_user_logged_in() ) {
      $current_user = wp_get_current_user();
      $role = array_shift( $current_user->roles );
      if ( 'true' == $this->_get_options( 'ignore_role_' . $role ) )
        return $this->_output_or_return( "<!-- Qeado Tracking Plugin is set to ignore your user role -->", $output );
    }

    // If $admin is true (we're in the admin_area), and we've been told to ignore_admin_area, return without inserting code.
    if (is_admin() && (!isset($wqt['ignore_admin_area']) || $wqt['ignore_admin_area'] != 'false'))
      return $this->_output_or_return( "<!-- Your Qeado Tracking Plugin is set to ignore Admin area -->", $output );

    $custom_vars = array(
      "mp('create','" . $tracking_id . "');",
    );
    if (!isset($wqt['track_social']) || $wqt['track_social'] != 'false') {
      $custom_vars[] = "mp('trackSocial');";
    }
    if (!isset($wqt['track_form']) || $wqt['track_form'] != 'false') {
      $custom_vars[] = "mp('trackForm');";
    }
    if (!isset($wqt['track_downloads']) || $wqt['track_downloads'] != 'false') {
      $custom_vars[] = "mp('trackDownloads');";
    }

    if (!isset($wqt['track_contact_clicks']) || $wqt['track_contact_clicks'] === 'true') {
      $custom_vars[] = "mp('trackContactClicks');";
    }

    $async_code = "<script type='text/javascript'>
(function(w,d,f,s,t,i) {
 w['Mpf'] = f;
 w['Mps'] = s;
 w[f] = w[f] || function() {
   (w[f].q = w[f].q || []).push(arguments)
 }
 t = d.createElement('script');
 i = d.getElementsByTagName('script')[0];
 t.async = 1;
 t.src = s;
 i.parentNode.insertBefore(t,i);
})(window, document, 'mp', 'https://collect.qeado.com/collect.js');

%custom_vars%
mp('event','pageview');
</script>";
    $custom_vars_string = implode( "\r\n", $custom_vars );

    $async_code = str_replace( '%custom_vars%', $custom_vars_string, $async_code );

    return $this->_output_or_return( $async_code, $output );

  }

  /**
   * Used to get one or all of our plugin options
   *
   * @param string[optional] $option - Name of options you want.  Do not use if you want ALL options
   * @return array of options, or option value
   */
  private function _get_options( $option = null, $default = false ) {

    $o = get_option('wqt');

    if (isset($option)) {

      if (isset($o[$option])) {
          return $o[$option];
      } else {
        if ( 'ignore_role_' == substr( $option, 0, 12 ) ) {
          global $wp_roles;
          // Backwards compat for when the tracking information was stored as a cap
          $maybe_role = str_replace( 'ignore_role_', '', $option );
          if ( isset( $wp_roles->roles[$maybe_role] ) ) {
            if ( isset( $wp_roles->roles[$maybe_role]['capabilities']['wqt_no_track'] ) && $wp_roles->roles[$maybe_role]['capabilities']['wqt_no_track'] )
              return 'true';
          }
          return false;
        }
        return $default;
      }
    } else {
      return $o;
    }
  }

  public function add_plugin_page_links( $links, $file ){
    if ( plugin_basename( __FILE__ ) == $file ) {
      $link = '<a href="' . admin_url( 'options-general.php?page=' . self::$page_slug ) . '">' . __( 'Settings', 'wp-qeado-tracking' ) . '</a>';
      array_unshift( $links, $link );
    }
    return $links;
  }

}

global $wp_qeado_tracking;
$wp_qeado_tracking = qeadoTracking::get_instance();
