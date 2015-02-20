<?php

/**
 * Plugin Name: FAU Video-Player
 * Description: Shortcode für Videos vom Videoportal
 * Version: 1.1
 * Author: RRZE-Webteam
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */

/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action('plugins_loaded', array('FAU_Video_Player', 'instance'));

register_activation_hook(__FILE__, array('FAU_Video_Player', 'activate'));
register_deactivation_hook(__FILE__, array('FAU_Video_Player', 'deactivate'));

class FAU_Video_Player {

    const version = '1.2';
    const option_name = '_fau_video_player';
    const version_option_name = '_fau_video_player_version';
    const textdomain = 'fau-video-player';
    const php_version = '5.3'; // Minimal erforderliche PHP-Version
    const wp_version = '4.0'; // Minimal erforderliche WordPress-Version
    static $embedscript;
    private $videoportal = array('www.video.uni-erlangen.de', 'www.video.fau.de', 'video.fau.de', 'www.fau-tv.de', 'fau-tv.de', 'www.fau.tv', 'fau.tv');

    protected static $instance = null;

    public static function instance() {

        if (null == self::$instance) {
            self::$instance = new self;
            self::$instance->init();
        }

        return self::$instance;
    }

    public function init() {
        load_plugin_textdomain(self::textdomain, false, dirname(plugin_basename(__FILE__)) . '/languages/');

        add_shortcode('fauvideo', array($this, 'shortcode'));
	// if (self::$embedscript==true) {
	 add_action('init', array($this, 'register_scripts'));
        //add_filter('oembed_fetch_url', array($this, 'oembed_url_filter'), 10, 3);
//	}
	//add_action('wp_footer', array($this, 'print_script'));
	
    }

    public function register_scripts() {      
        wp_enqueue_script('fauvideo', plugins_url('/', __FILE__) . 'js/jwplayer.js', false, self::version);
    }

    public function print_script() {
	//	if ( ! self::$embedscript )
	//		return;
		wp_print_scripts('fauvideo');
	}
    
    public static function activate() {
        self::version_compare();
        update_option(self::version_option_name, self::version);
    }

    private static function version_compare() {
        $error = '';

        if (version_compare(PHP_VERSION, self::php_version, '<')) {
            $error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain), PHP_VERSION, self::php_version);
        }

        if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
            $error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain), $GLOBALS['wp_version'], self::wp_version);
        }

        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), false, true);
            wp_die($error);
        }
    }

    public static function update_version() {
        if (get_option(self::version_option_name, null) != self::version)
            update_option(self::version_option_name, self::version);
    }

    public function shortcode($atts) {
        $default = array(
            'url' => '',
            'image' => '',
            'width' => '',
            'height' => '',
	    'showtitle' => false,
	    'showinfo'	=> false,
	    'titletag' => ''
        );
        $atts = shortcode_atts($default, $atts);       
        extract($atts);
        if (empty($url)) {
            return __('Es wurde keine Adresse zu einem Video eingegeben.', self::textdomain);
        } else {
	    $rand = rand();
            $host = parse_url($url, PHP_URL_HOST);    
            if (in_array($host, $this->videoportal)) {
                $oembed_url = 'http://www.video.uni-erlangen.de/services/oembed/?url=' . $url . '&format=json';
                $video = json_decode(wp_remote_retrieve_body(wp_safe_remote_get($oembed_url)), true);       
		
		$output = '';
		$file = '';
                if (isset($video['file'])) {
                    $file = $video['file'];       
		    
		    self::$embedscript = true;
		    
		    if (filter_var($image, FILTER_VALIDATE_URL)) {
			// nehme $image von der shortcodeeingabe
		    } else {
			if (empty($image)) {
			    if (isset($video['image'])) {
				$image = $video['image'];
			    } else {
				$image = plugins_url('/', __FILE__) . 'img/itunes_fau_800x400.png';
			    } 

			}
		    }
		    if (isset($video['width']) && isset($video['height'])) {
			if (empty($width)) {
			    if (empty($height)) {
				$width = $video['width'];
				$height = $video['height'];
			    } else {
				$width = ($video['width'] * $height) / $video['height'];
			    }
			} else {
			    if (empty($height)) {
				$height = ($video['height'] * $width) / $video['width'];                        
			    }
			}
		    } 
		    if (empty(trim($titletag))) {
			$titletag = 'h2';
		    }
		    if ($showinfo==true) {
			$showtitle = true;
		    }
		    $output .= '<div class="fauvideo-'.$rand.'" itemscope itemtype ="http://schema.org/Movie">';
		    if (($showtitle ==true) && isset($video['title'])) {
			$output .= "<$titletag itemprop=\"name\">".$video['title']."</$titletag>";
		    }
		    
		    $output .= '<meta itemprop="contentUrl" content="'.$file.'">';
		    $output .= '<meta itemprop="height" content="'.$video['height'].'">';
		    $output .= '<meta itemprop="width" content="'.$video['width'].'">';
		    if (isset($video['image'])) {
			$output .= '<meta itemprop="thumbnailUrl" content="'.$video['image'].'">';
		    }
		    $loading = __('Video wird geladen...', self::textdomain);

		    $output .= "<div id='video-" . $rand . "'>" . $loading . "</div>\n<script type='text/javascript'>\n   jwplayer('video-".$rand."').setup({\n    flashplayer: '" . plugins_url('/', __FILE__) . 'js/player.swf' . "',\n    skin: '" . plugins_url('/', __FILE__) . 'skin/glow.zip' . "',\n    file: '" . $file . "',\n    image: '" . $image . "',\n    width: " . $width . ",\n    height: " . $height . "    });\n</script>";
		    
		    if ($showinfo==true) {
			 $output .= "<ul class=\"info\">\n";
			 if (isset($video['author_name'])) {
			    $output .= '<li>'.__('Vortrag',self::textdomain).': <span class="actor">'.$video['author_name'].'</span></li>'."\n";
			 }
			 $output .= '<li>'.__('Quelle',self::textdomain).': <a href="'.$url.'" class="isBasedOnUrl">'.$url.'</a></li>'."\n";
			 if (isset($video['provider_name'])) {
			    $output .= '<li>'.__('Copyright',self::textdomain).': <a href="'.$video['provider_url'].'"><span class="publisher">'.$video['provider_name'].'</span></a></li>'."\n";
			 }
			 $output .= "</ul>\n";
		    }
		    $output .= "</div>\n";
		    
		} else {
		    return __('Die angegebene URL lieferte keine Videodaten. Bitte verwenden Sie die Adresse aus dem Videoportal, in dem ein Video als solches auch angezeigt wird und keine Indexseite.', self::textdomain);
		}
		
		
		return $output;
            } else {
                return __('Es können nur Videos vom Videoportal eingebunden werden.', self::textdomain);
            }
        }
    }
    
}
