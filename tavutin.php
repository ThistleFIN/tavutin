<?php
/*
Plugin Name: 	Tavutin
Description: 	Lisäosa lisäämään automaattisen taivutuksen suomenkieliseen tekstiin.
Version: 		1.0.0
Author: 		Roosa Virta
Author URI: 	https://takiainen.fi
License: 		GPLv2 or later
License URI:	http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
require_once(__DIR__ . '/tavuttaja.php');

add_action('init', 'tavuttaja_init');

/**
 * Initializes the plugin by adding the appropriate filters.
 */
function tavuttaja_init(): void
{
	$options = get_option('tavutin_options');
	$hyphenate_content = !isset($options['hyphenate_content']) || $options['hyphenate_content'];
	$hyphenate_title = !isset($options['hyphenate_title']) || $options['hyphenate_title'];
	$hyphenate_excerpt = !isset($options['hyphenate_excerpt']) || $options['hyphenate_excerpt'];
	if ($hyphenate_content) {
		add_filter('the_content', 'tavuttaja');
	}
	if ($hyphenate_title) {
		add_filter('the_title', 'tavuttaja');
	}
	if ($hyphenate_excerpt) {
		add_filter('the_excerpt', 'tavuttaja');
	}
	
	// initialize it so it is ready when filters call.
	Tavuttaja::get_instance();
}


/**
 * Adds shy tags to the given content if the locale is Finnish and caches the result.
 *
 * @param string $content The content to process.
 * @return string The processed content.
 */
function tavuttaja($content): string
{
	if (get_locale() === 'fi') {
		$cache_key = 'tavuttaja_cache_' . md5($content);
		$cached_content = get_transient($cache_key);
		
		if ($cached_content !== false) {
			return $cached_content;
		}
		
		$tavuttaja = Tavuttaja::get_instance();
		$content = $tavuttaja->add_shy_tags($content);
		
		// Cache the hyphenated content for 12 hours.
		set_transient($cache_key, $content, 12 * HOUR_IN_SECONDS);
	}
	
	return $content;
}

add_action('admin_menu', 'tavutin_add_admin_menu');
add_action('admin_init', 'tavutin_settings_init');

/**
 * Adds Tavutin menu item to the WordPress admin menu.
 */
function tavutin_add_admin_menu()
{
	add_options_page('Tavutin', 'Tavutin', 'manage_options', 'tavutin', 'tavutin_options_page');
}

/**
 * Initializes Tavutin settings in the WordPress admin.
 */
function tavutin_settings_init()
{
	register_setting('tavutin_options', 'tavutin_options');
	
	add_settings_section(
		'tavutin_settings_section',
		'Tavutin Options',
		'tavutin_settings_section_callback',
		'tavutin_options'
	);
	
	add_settings_field(
		'hyphenate_content',
		'Lisää tavutus sisältöön.',
		'hyphenate_content_render',
		'tavutin_options',
		'tavutin_settings_section'
	);
	
	add_settings_field(
		'hyphenate_title',
		'Lisää tavutus otsikkoon',
		'hyphenate_title_render',
		'tavutin_options',
		'tavutin_settings_section'
	);
	
	add_settings_field(
		'hyphenate_excerpt',
		'Lisää tavutus tiivistelmään',
		'hyphenate_excerpt_render',
		'tavutin_options',
		'tavutin_settings_section'
	);
}

/**
 * Renders the Tavutin settings section callback.
 */
function tavutin_settings_section_callback()
{
	echo 'Mihin automaattinen taivutus lisätään?';
}

/**
 * Renders the hyphenate content setting.
 */
function hyphenate_content_render()
{
	$options = get_option('tavutin_options');
	$hyphenate_content = isset($options['hyphenate_content']) ? $options['hyphenate_content'] : true;
	?>
	<input type='checkbox' name='tavutin_options[hyphenate_content]' <?php checked($hyphenate_content, true); ?> value='1'>
	<?php
}

/**
 * Renders the hyphenate title setting.
 */
function hyphenate_title_render()
{
	$options = get_option('tavutin_options');
	$hyphenate_title = isset($options['hyphenate_title']) ? $options['hyphenate_title'] : true;
	?>
	<input type='checkbox' name='tavutin_options[hyphenate_title]' <?php checked($hyphenate_title, true); ?> value='1'>
	<?php
}

/**
 * Renders the hyphenate excerpt setting.
 */
function hyphenate_excerpt_render()
{
	$options = get_option('tavutin_options');
	$hyphenate_excerpt = isset($options['hyphenate_excerpt']) ? $options['hyphenate_excerpt'] : true;
	?>
	<input type='checkbox' name='tavutin_options[hyphenate_excerpt]' <?php checked($hyphenate_excerpt, true); ?> value='1'>
	<?php
}

/**
 * Displays the Tavutin options page.
 */
function tavutin_options_page()
{
	?>
	<form action='options.php' method='post'>
		<?php
		settings_fields('tavutin_options');
		do_settings_sections('tavutin_options');
		submit_button();
		?>
	</form>
	<?php
}