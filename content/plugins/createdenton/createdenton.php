<?php
/**
 * Plugin Name: CreateDenton
 * Description: Custom plugin for CreateDenton
 * Version: 1.0-alpha
 * Author: Patrick Daly
 * Author URI: http://developdaly.com
 * Author Email: patrick@developdaly.com
 */

require_once(WP_PLUGIN_DIR . "/" . basename(dirname(__FILE__)) . "/user-taxonomies.php");

// After user registration, login user
add_action( 'gform_user_registered', 'pi_gravity_registration_autologin', 10, 4 );

// Change Gravity Forms upload path
add_filter("gform_upload_path", "change_upload_path", 10, 2);

// Update avatar in user meta via Gravity Forms
add_action("gform_after_submission", "cd_update_avatar", 10, 2);

add_action( 'wp_footer', 'cd_first_timer' );

/**
 * Auto login after registration.
 */
function pi_gravity_registration_autologin( $user_id, $user_config, $entry, $password ) {
	$user = get_userdata( $user_id );
	$user_login = $user->user_login;
	$user_password = $password;

    wp_signon( array(
		'user_login'	=> $user_login,
		'user_password'	=> $user_password,
		'remember'		=> false
    ) );
}

function cd_first_timer() {
	$first_timer = $_COOKIE['create_denton_first_timer'];
	if ( empty( $first_timer ) ) {
	    setcookie( 'create_denton_first_timer', 'no' );
	    get_template_part( 'first-timer' );
	    exit();
	}
}

/**
 * Checks if the user is valid (has all the right info) and returns boolean.
 *
 */
function cd_is_valid_user( $user_id ) {

	if( cd_user_errors( $user_id ) == null )
		return true;
	else
		return false;
}

/**
 * Displays the errors a user has
 * (i.e. missing data required to be a valid user)
 */
function cd_user_errors( $user_id ) {

	$user_data		= get_userdata( $user_id );
	$email			= $user_data->user_email;

	$user_meta		= get_user_meta( $user_id );
	$first_name		= isset( $user_meta['first_name'][0] );
	$last_name		= isset( $user_meta['last_name'][0] );
	$zip			= isset( $user_meta['user_zip'][0] );
	$primary_job	= isset( $user_meta['user_primary_job'][0] );
	$avatar_type	= isset( $user_meta['avatar_type'][0] );

	//if( isset( $user_meta['avatar'][0] ) )
	//	$avatar		= cd_get_avatar( $user_id );
	//else
	//	$avatar		= '';

	$errors = array();

	if ( $email == '' )
		$errors[] = ' email';

	if ( !$first_name )
		$errors[] = ' first name';

	if ( !$last_name )
		$errors[] = ' last name';

	if ( !$zip )
		$errors[] = ' zip code';

	if ( !$primary_job )
		$errors[] = ' primary job';

	//if ( !$avatar_type )
	//	$errors[] = ' avatar';

	//if ( cd_has_header_error( $avatar ) )
	//	$errors[] = ' broken avatar';

	$output = implode( ',', $errors );

	return $output;
}

function cd_clean_username( $user_id ) {
	$user_info = get_userdata( $user_id );

	$username = strtolower( $user_info->user_login );

	$output = preg_replace("![^a-z0-9]+!i", "-", $username );

	return $output;
}

// this function is called by both filters and returns the requested user meta of the current user
function populate_usermeta($meta_key){
    global $current_user;
    return $current_user->__get($meta_key);
}

function cd_get_oneall_user( $user_id, $attribute = '' ) {

	//Read settings
	$settings = get_option ('oa_social_login_settings');

	//API Settings
	$api_connection_handler = ((!empty ($settings ['api_connection_handler']) AND $settings ['api_connection_handler'] == 'fsockopen') ? 'fsockopen' : 'curl');
	$api_connection_use_https = ((!isset ($settings ['api_connection_use_https']) OR $settings ['api_connection_use_https'] == '1') ? true : false);

	$site_subdomain = (!empty ($settings ['api_subdomain']) ? $settings ['api_subdomain'] : '');
	$site_public_key = (!empty ($settings ['api_key']) ? $settings ['api_key'] : '');
	$site_private_key = (!empty ($settings ['api_secret']) ? $settings ['api_secret'] : '');

	//API Access Domain
	$site_domain = $site_subdomain . '.api.oneall.com';

	$user_token = get_user_meta($user_id, 'oa_social_login_user_token', true);

	//Connection Resource
	$resource_uri = 'https://' . $site_domain . '/users/' . $user_token . '.json';

	// Initializing curl
	$ch = curl_init($resource_uri);

	// Configuring curl options
	$options = array(CURLOPT_URL => $resource_uri, CURLOPT_HEADER => 0, CURLOPT_USERPWD => $site_public_key . ":" . $site_private_key, CURLOPT_TIMEOUT => 15, CURLOPT_VERBOSE => 0, CURLOPT_RETURNTRANSFER => 1, CURLOPT_SSL_VERIFYPEER => 1, CURLOPT_FAILONERROR => 0);

	// Setting curl options
	curl_setopt_array($ch, $options);

	// Getting results
	$result = curl_exec($ch);

	$data = json_decode($result);

	$output = '';

	if( isset( $data->response->result ) ){

		if( $attribute == '' ){
			$output = isset( $data->response->result->data->user->identities );
		}

		if( $attribute == 'thumbnail' && isset( $data->response->result->data->user->identities->identity[0]->thumbnailUrl ) ) {
			$output = $data->response->result->data->user->identities->identity[0]->thumbnailUrl;
		}

		if( $attribute == 'picture' && isset( $data->response->result->data->user->identities->identity[0]->pictureUrl ) ) {
			$output = $data->response->result->data->user->identities->identity[0]->pictureUrl;
		}

	} else {
		$output = get_avatar_url( get_avatar( $user_id, 150 ) );
	}

	return $output;

}

function get_avatar_url($get_avatar){
    preg_match("/src='(.*?)'/i", $get_avatar, $matches);
    return $matches[1];
}

function cd_has_header_error( $url = '' ) {

	$file_headers = @get_headers( $url );
	if ( strpos( $file_headers[0], '200' ) == false )
		return true;

	return false;

}