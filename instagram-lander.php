<?php
/*
Plugin Name: Lander For IG
Plugin URI: https://wordpress.org/plugins/lander-for-instagram
Version: 1.0.9
Description: Landing page featuring a mirror of your instagram feed with links to related posts
Author: James Low
Author URI: http://jameslow.com
*/

class Instagram_Lander {
	public static $PREFIX = 'instagram-lander';
	public static $GORUP = 'instagram-lander-settings-group';
	public static $APIKEY = '';
	
	public static function add_hooks() {
		add_action('admin_menu', array('Instagram_Lander', 'admin_menu'));
		add_action('admin_enqueue_scripts', array('Instagram_Lander', 'include_cssjs'));
		add_shortcode('instagram_lander', array('Instagram_Lander', 'do_shortcode'));
		add_action('rest_api_init', function () {
			register_rest_route( 'instagram-lander/v1', 'api', array(
				'methods' => 'GET,POST',
				'callback' => array('Instagram_Lander', 'ajax'),
				'args' => array()
			));
		});
	}
	public static function ajax() {
		if (current_user_can('administrator')) {
			$method = $_REQUEST['method'];
			if ($method == 'accesstoken') {
				return json_decode(self::accesstoken($_REQUEST['appid'], $_REQUEST['secret'], $_REQUEST['token']));
			} else {
				return array('error' => 'Invalid method');
			}
		} else {
			return array('error' => 'No Permissions');
		}
	}
	public static function include_cssjs() {
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-dialog');
		//wp_localize_script( 'wp-api', 'wpApiSettings', array( 'root' => esc_url_raw( rest_url() ), 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
	}
	public static function admin_menu() {
		//add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);
		add_options_page('Instagram Lander', 'Instagram Lander', 'manage_options', self::$PREFIX, array('Instagram_Lander', 'options_page'));
		add_action('admin_init', array('Instagram_Lander', 'admin_init'));
	}
	public static function admin_init() {
		register_setting(self::$GORUP, self::$PREFIX.'-facebook-app');
		register_setting(self::$GORUP, self::$PREFIX.'-facebook-secret');
		register_setting(self::$GORUP, self::$PREFIX.'-facebook-page');
		register_setting(self::$GORUP, self::$PREFIX.'-facebook-id');
		register_setting(self::$GORUP, self::$PREFIX.'-facebook-token');
		register_setting(self::$GORUP, self::$PREFIX.'-facebook-expiry');
		register_setting(self::$GORUP, self::$PREFIX.'-instagram-username');
		register_setting(self::$GORUP, self::$PREFIX.'-instagram-facebook');
		register_setting(self::$GORUP, self::$PREFIX.'-default-link');
	}
	public static function require_http() {
		if (class_exists('PageApp')) {
			PageApp::require_http();
		} else {
			require_once 'httplib.php';
		}
	}
	public static function accesstoken($appid, $secret, $token) {
		self::require_http();
		$http = new HTTPRequest('https://graph.facebook.com/oauth/access_token?grant_type=fb_exchange_token&client_id='.$appid.'&client_secret='.$secret.'&fb_exchange_token='.$token);
		return $http->Get();
	}
	public static function facebook($path, $access_token, $params = null) {
		//https://stackoverflow.com/questions/13614280/querying-the-graph-api-with-curl
		self::require_http();
		$http = new HTTPRequest('https://graph.facebook.com/v3.1'.$path);
		$header = array('Authorization' => 'Bearer '.$access_token);
		return $http->Get($params ? $params : array(), true, $header);
	}
	public static function do_shortcode($atts) {
		$a = shortcode_atts(array(
			'access_token' => get_option(self::$PREFIX.'-facebook-token'),
			'instagram_fbid' => get_option(self::$PREFIX.'-instagram-facebook'),
			'default_link' => get_option(self::$PREFIX.'-default-link')
		), $atts );
		
		if ($a['access_token'] == '' || $a['instagram_fbid'] == '') {
			return 'Please set access token and instagram facebok id';
		} else {
			//https://developers.facebook.com/docs/instagram-api/reference/media/
			$result = self::facebook('/'.$a['instagram_fbid'].'/media?fields=caption,media_type,media_url,permalink,thumbnail_url',$a['access_token']);
			$json = json_decode($result['body']);
			$rows = $json->data;
			$html = '<div id="instagram_lander">
<style>
.instagram_lander div {
	display: inline-block;
	background-position: center;
	background-size: cover;
	background-repeat: no-repeat;
	width: 31%;
	margin-right: 3.5%;
	margin-bottom: 3.5%;
}
.instagram_lander div:nth-child(3n) {
	margin-right: 0;
}
.instagram_lander div:hover {
	opacity: 0.5;
}
.instagram_lander img {
	width:100%;
	height:auto;
}
</style>
<div class="instagram_lander">
';
			$default_link = $a['default_link'];
			
			foreach ($rows as $row) {
				$caption = trim(str_replace("\n", ' ', $row->caption));
				$parts = explode(' ', $caption);
				//$target = $default_link == '' ? ' target="_blank"' : ''; //For when we were linking back
				//$link = $default_link == '' ? $row->permalink : $default_link;
				$target = '';
				$link = $default_link == '' ? get_site_url() : $default_link;
				if (count($parts) > 0) {
					$last = $parts[count($parts)-1];
					if (strpos($last, 'http') === 0) {
						$link = $last;
						$target = '';
					}
				}
				//IMAGE,CAROUSEL_ALBUM,VIDEO
				$html .= '<div style="background-image:url('.(isset($row->thumbnail_url)?$row->thumbnail_url:$row->media_url).');"><a href="'.$link.'"'.$target.'><img src="'.plugin_dir_url(__FILE__).'pixel.png" /></a></div>';
			}
			$html .= '</div></div>';
			return $html;
		}
	}
	public static function options_page() { ?>
<div class="wrap">
<script>
	(function(d, s, id){
	 var js, fjs = d.getElementsByTagName(s)[0];
	 if (d.getElementById(id)) return;
	 js = d.createElement(s); js.id = id;
	 js.src = "https://connect.facebook.net/es_LA/sdk.js";
	 fjs.parentNode.insertBefore(js, fjs);
	}(document, 'script', 'facebook-jssdk'));
	function instagram_lander_init() {
		var appid = ''+jQuery('[name="instagram-lander-facebook-app"]').val();
		var secret = ''+jQuery('[name="instagram-lander-facebook-secret"]').val();
		if (appid == '' || secret == '') {
			alert('Please enter your Facebook App Id and App Secret');
		} else {
			FB.init({
				appId            : appid,
				autoLogAppEvents : true,
				xfbml            : true,
				version          : 'v3.1'
			});
			FB.getLoginStatus(function(response) {
				 if (response.authResponse) {
					instagram_lander_pages();
				 } else {
					FB.login(function(response) {
						if (response.authResponse) {
							instagram_lander_pages();
						} else {
							alert('You must be logged');
						}
					},{scope:'pages_show_list,instagram_basic,business_management'});
				 }
			});
		}
	}
	function instagram_lander_pages() {
		FB.api('/me/accounts?limit=1000', function(response) {
			if (response && response.data) {
				var pages = response.data;
				var html = '';
				for (var i in pages) {
					var page = pages[i];
					html += '<div style="cursor:pointer !important;" data-id="'+page.id+'" data-access-token="'+page.access_token+'" onclick="return instagram_lander_instagram(this);">'+page.name+'</div>';
				}
				jQuery('#instagram-lander-dialog').attr('title','Select Page');
				jQuery('#instagram-lander-dialog').html(html);
				jQuery('#instagram-lander-dialog').dialog();
			} else {
				console.log(response);
				alert('Error getting pages from Facebook');
			}
		});
	}
	function instagram_lander_instagram(element) {
		jQuery('#instagram-lander-dialog').dialog('close');
		var row = jQuery(element);
		var accesstoken = row.attr('data-access-token');
		var id = row.attr('data-id');
		instagram_lander_access_token(accesstoken);
		
		jQuery('[name="instagram-lander-facebook-page"]').val(row.text());
		jQuery('[name="instagram-lander-facebook-id"]').val(id);
		//jQuery('[name="instagram-lander-facebook-token"]').val(accesstoken);

		//Must Instagram business account
		/*FB.api('/me?fields=instagram_accounts{username}', {access_token : accesstoken}, function(response) {
			if (response && response.instagram_accounts && response.instagram_accounts.data && response.instagram_accounts.data.length > 0) {
				var instagram = response.instagram_accounts.data;
				if (instagram.length == 1) {
					jQuery('[name="instagram-lander-instagram-facebook"]').val(instagram[0].id);
					jQuery('[name="instagram-lander-instagram-username"]').val(instagram[0].username);
				} else {
					var html = '';
					for (var i in instagram) {
						var account = instagram[i];
						html += '<div style="cursor:pointer !important;" data-id="'+account.id+'" onclick="return instagram_lander_instagram_select(this);">'+account.username+'</div>';
					}
					jQuery('#instagram-lander-dialog').attr('title','Select Account');
					jQuery('#instagram-lander-dialog').html(html);
					jQuery('#instagram-lander-dialog').dialog();
				}
			} else {
				console.log(response);
				alert('Error getting instagram pages');
			}
		});*/
		FB.api('/me?fields=instagram_business_account', {access_token : accesstoken}, function(response) {
			if (response && response.instagram_business_account && response.instagram_business_account.id) {
				var instagram = response.instagram_business_account;
				jQuery('[name="instagram-lander-instagram-facebook"]').val(instagram.id);
				//jQuery('[name="instagram-lander-instagram-username"]').val(instagram.username);
			} else {
				console.log(response);
				alert('Error getting instagram pages');
			}
		});
	}
	function instagram_lander_instagram_select(element) {
		jQuery('#instagram-lander-dialog').dialog('close');
		var row = jQuery(element);
		var accesstoken = row.attr('data-access-token');
		var id = row.attr('data-id');
		jQuery('[name="instagram-lander-instagram-facebook"]').val(id);
		jQuery('[name="instagram-lander-instagram-username"]').val(row.text());
	}
	function instagram_lander_access_token(accesstoken) {
		var appid = ''+jQuery('[name="instagram-lander-facebook-app"]').val();
		var secret = ''+jQuery('[name="instagram-lander-facebook-secret"]').val();
		
		jQuery.ajax({
			url: '<?php echo esc_url_raw(rest_url()); ?>' + 'instagram-lander/v1/api',
			method: 'POST',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>' );
			},
			data:{
				'appid':appid,
				'secret':secret,
				'token':accesstoken,
				'method':'accesstoken'
			}
		}).done(function(response) {
			if (response && response.error) {
				alert(response.error);
			} else {
				console.log(response);
				jQuery('[name="instagram-lander-facebook-token"]').val(response.access_token);
				jQuery('[name="instagram-lander-facebook-expiry"]').val((new Date()).getTime() + (response.expires_in * 1000));
			}
		});
	}
</script> 

<h1>Instagram Lander</h1>
<ol>
<li>Create and add your Facebook App Id</li>
<li>Add your Facebook App Secret</li>
<li>Click lookup: <button onclick="return instagram_lander_init();">Lookup Info</button></li>
<li>If your browser blocks popup, you may have to enable it and click lookup again</li>
</ol>
<form method="post" action="options.php">
<?php settings_fields(self::$GORUP); ?>
<?php do_settings_sections(self::$GORUP); ?>

<table class="form-table">
	<tr valign="top">
		<th scope="row">Facebook App Id</th>
		<td><?php echo self::setting('facebook-app','App Id'); ?>
		<?php echo self::setting('facebook-secret','App Secret',true); ?></td>
	</tr>
	<tr valign="top">
		<th scope="row">Facebook Page</th>
		<td><?php echo self::setting('facebook-page','Name'); ?>
		<?php echo self::setting('facebook-id','Id'); ?></td>
	</tr>
	<tr valign="top">
		<th scope="row">Instagram Token</th>
		<td><?php echo self::setting('instagram-facebook','Instagram Business Id'); ?>
		<?php echo self::setting('facebook-token','Access Token',true); ?>
		<?php //echo self::setting('facebook-expiry','Expiry'); ?></td>
	</tr>
	<!--<tr valign="top">
		<th scope="row">Instagram Username</th>
		<td><?php echo self::setting('instagram-username','Username'); ?>
		</td>
	</tr>-->
	<tr valign="top">
		<th scope="row">Default Link</th>
		<td><?php echo self::setting('default-link','URL'); ?></td>
	</tr>
</table>
<?php submit_button(); ?>
</form>

<div id="instagram-lander-dialog" title="Select" style="display:none;"></div>
</div>
<?php }
	public static function setting($id, $placeholder = '', $password = false) {
		return '<input placeholder="'.$placeholder.'" type="'.($password?'password':'text').'" name="'.self::$PREFIX.'-'.$id.'" value="'.esc_attr(self::option($id)).'" />';
	}
	public static function option($id) {
		return get_option(self::$PREFIX.'-'.$id);
	}
}
Instagram_Lander::add_hooks();