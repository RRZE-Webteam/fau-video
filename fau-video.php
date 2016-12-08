<?php
/**
 * Plugin Name: FAU Video-Player
 * Description: Shortcode für Videos vom Videoportal
 * Version: 1.5.4
 * Author: RRZE-Webteam
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 * Text Domain: fau-video-player
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

class FAU_Video_Player {

    const option_name = '_fau_video_player';
    const php_version = '5.6'; // Minimal erforderliche PHP-Version
    const wp_version = '4.7'; // Minimal erforderliche WordPress-Version

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
        // Sprachdateien werden eingebunden.
        self::load_textdomain();

        add_action('widgets_init', create_function('', 'return register_widget("FAUVideoWidget");'));
        
        add_shortcode('fauvideo', array($this, 'shortcode'));
    }

    public function create_html($videourl = '', $placeholderimage = '', $width = '', $height = '', $showtitle = false, $showinfo = false, $titletag = 'h3') {
     if (filter_var($videourl, FILTER_VALIDATE_URL)) {
	    $url = $videourl;
	    
	    $image = ''; 
	    if (filter_var($placeholderimage, FILTER_VALIDATE_URL)) {
		$image = $placeholderimage;
	    }
	    
	    $rand = rand();
            $host = parse_url($url, PHP_URL_HOST);    
            if (in_array($host, $this->videoportal)) {
                $oembed_url = 'http://www.video.uni-erlangen.de/services/oembed/?url=' . $url . '&format=json';
                $video = json_decode(wp_remote_retrieve_body(wp_safe_remote_get($oembed_url)), true);       
		
		$output = '';
		$file = '';
                
                if (isset($video['file'])) {
                    $file = $video['file'];
                    
		    if (filter_var($image, FILTER_VALIDATE_URL)) {
			// nehme $image von der shortcodeeingabe
		    } else {
			if (empty($image)) {
			    if (isset($video['image'])) {
				$image = $video['image'];
			    } else {
				$image = plugins_url('/', __FILE__) . 'images/itunes_fau_800x400.png';
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
		    
		    if ($showinfo) {
			$showtitle = true;
		    }
                    
		    $output .= '<div class="fauvideo-'.$rand.'" itemscope itemtype ="http://schema.org/Movie">';
		    if (isset($video['title'])) {
			$output .= "<$titletag itemprop=\"name\"";
                        
			if (!$showtitle) {
			    $output .= " class=\"screen-reader-text\"";
			}
                        
			$output .= ">".$video['title']."</$titletag>";
		    }
		    
		    $output .= '<meta itemprop="contentUrl" content="'.$file.'">';
		    $output .= '<meta itemprop="height" content="'.$video['height'].'">';
		    $output .= '<meta itemprop="width" content="'.$video['width'].'">';
		    if (isset($video['image'])) {
			$output .= '<meta itemprop="thumbnailUrl" content="'.$video['image'].'">';
		    }
		    
		    $loading = '<a href="'.$file.'"><img src="'.$image.'" alt=""></a>';
		 
                    echo $output;
                    
                    echo do_shortcode('[video preload="none" width="' . $width . '" height="' . absint($height - 20) . '" src="' . $file . '" poster="' . $image . '"][/video]');
                    
                    $output='';
		    
		    if ($showinfo) {                       
			 $output .= "<ul class=\"info\">\n";
                         
			 if (isset($video['author_name'])) {
			    $output .= '<li>'.__('Autor','fau-video').': <span class="actor">'.$video['author_name'].'</span></li>'."\n";
			 }
                         
			 $output .= '<li>'.__('Quelle','fau-video').': <a href="'.$url.'" class="isBasedOnUrl">'.$url.'</a></li>'."\n";
			 if (isset($video['provider_name'])) {
			    $output .= '<li>'.__('Copyright','fau-video').': <a href="'.$video['provider_url'].'"><span class="publisher">'.$video['provider_name'].'</span></a></li>'."\n";
			 }
                         
			 $output .= "</ul>\n";
		    }
                    
		    $output .= "</div>\n";
		   
                    echo $output;
		} else {
		    _e('Die angegebene URL lieferte keine Videodaten. Bitte verwenden Sie die Adresse aus dem Videoportal, in dem ein Video als solches auch angezeigt wird und keine Indexseite.', 'fau-video');
		}
		
		return $output;
            } else {
                _e('Es können nur Videos vom Videoportal eingebunden werden.', 'fau-video');
            }
	    
	} else {
	    _e('Fehlerhafte URL','fau-video');
	}
    }

    public function shortcode($atts) {
        $default = array(
            'url' => '',
            'image' => '',
            'width' => '',
            'height' => '',
            'showtitle' => false,
            'showinfo' => false,
            'titletag' => 'h2'
        );
        $atts = shortcode_atts($default, $atts);
        extract($atts);

        return $this->create_html($url, $image, $width, $height, $showtitle, $showinfo, $titletag);
    }

    // Einbindung der Sprachdateien.
    private static function load_textdomain() {    
        load_plugin_textdomain('cms-basis', false, sprintf('%s/languages/', dirname(plugin_basename(__FILE__))));
    }
    
    public static function activate() {
        // Sprachdateien werden eingebunden.
        self::load_textdomain();
        
        self::system_requirements();
    }

    /*
     * Überprüft die minimal erforderliche PHP- u. WP-Version.
     * @return void
     */    
    private static function system_requirements() {
        $error = '';

        if (version_compare(PHP_VERSION, self::php_version, '<')) {
            $error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', 'fau-video'), PHP_VERSION, self::php_version);
        }

        if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
            $error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', 'fau-video'), $GLOBALS['wp_version'], self::wp_version);
        }

        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), false, true);
            wp_die($error);
        }
    }

}

class FAUVideoWidget extends WP_Widget {

    function __construct() {
        $widget_ops = array('classname' => 'FAUVideoWidget', 'description' => __('Video aus dem Videoportal einbinden', 'fau-video'));
        parent::__construct('FAUVideoWidget', 'FAU Videoportal', $widget_ops);
    }

    function form($instance) {

        if ($instance) {
            $title = esc_attr($instance['title']);
            $url = esc_url($instance['url']);
            $showtitle = absint($instance['showtitle']) ? true : false;
            $showinfo = absint($instance['showinfo']) ? true : false;
            $width = absint($instance['width']);
            $height = absint($instance['height']);
            $imageurl = esc_url($instance['imageurl']);
        } else {
            $title = '';
            $url = '';
            $imageurl = '';
            $showtitle = 0;
            $showinfo = 0;
            $width = 200;
            $height = 150;
        }

        echo '<p>';
        echo '<label for="' . $this->get_field_id('title') . '">' . __('Titel', 'fau-video') . ': </label>';
        echo '<input type="text" id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" value="' . $title . '">';
        echo '</p>';
        echo '<p>';
        echo '<label for="' . $this->get_field_id('url') . '">' . __('Video-URL', 'fau-video') . ': </label>';
        echo '<input size="40" type="text" id="' . $this->get_field_id('url') . '" name="' . $this->get_field_name('url') . '" value="' . $url . '">';
        echo '</p>';

        echo '<p>';
        echo '<label for="' . $this->get_field_id('width') . '">' . __('Breite', 'fau-video') . ': </label>';
        echo '<input  size="4" type="text" id="' . $this->get_field_id('width') . '" name="' . $this->get_field_name('width') . '" value="' . $width . '">';
        echo '</p>';
        echo '<p>';
        echo '<label for="' . $this->get_field_id('height') . '">' . __('Höhe', 'fau-video') . ': </label>';
        echo '<input size="4" type="text" id="' . $this->get_field_id('height') . '" name="' . $this->get_field_name('height') . '" value="' . $height . '">';
        echo '</p>';
        echo '<p>';
        echo '<label for="' . $this->get_field_id('imageurl') . '">' . __('Vorschaubild-URL', 'fau-video') . ': </label>';
        echo '<input size="40" type="text" id="' . $this->get_field_id('imageurl') . '" name="' . $this->get_field_name('imageurl') . '" value="' . $imageurl . '">';
        echo '</p>';
        ?>
        <p>
            <select class="onoff" name="<?php echo $this->get_field_name('showtitle'); ?>" id="<?php echo $this->get_field_id('showtitle'); ?>">
                <option value="0" <?php selected(false, $showtitle); ?>><?php _e('Aus', 'fau-video'); ?></option>
                <option value="1" <?php selected(true, $showtitle); ?>><?php _e('An', 'fau-video'); ?></option>
            </select>
            <label for="<?php echo $this->get_field_id('showtitle'); ?>">
                <?php _e('Zeige auch Videotitel', 'fau-video'); ?>
            </label>
        </p>	
        <p>
            <select class="onoff" name="<?php echo $this->get_field_name('showinfo'); ?>" id="<?php echo $this->get_field_id('showinfo'); ?>">
                <option value="0" <?php selected(false, $showinfo); ?>>Aus</option>
                <option value="1" <?php selected(true, $showinfo); ?>>An</option>
            </select>
            <label for="<?php echo $this->get_field_id('showinfo'); ?>">
                <?php _e('Zeige Metainformationen und Videotitel', 'fau-video'); ?>
            </label>
        </p>
        <?php
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['url'] = esc_url($new_instance['url']);
        $instance['imageurl'] = esc_url($new_instance['imageurl']);
        $instance['showtitle'] = absint($new_instance['showtitle']) ? true : false;
        $instance['showinfo'] = absint($new_instance['showinfo']) ? true : false;        
        $instance['width'] = absint($new_instance['width']);
        $instance['height'] = absint($new_instance['height']);
        return $instance;
    }

    function widget($args, $instance) {
        extract($args, EXTR_SKIP);

        echo $before_widget;

        if (!empty($instance['title'])) {
            echo '<h2 class="small">' . $instance['title'] . '</h2>';
        }
        
        $url = esc_url($instance['url']);
        $imageurl = esc_url($instance['imageurl']);
        $showtitle = absint($instance['showtitle']) ? true : false;
        $showinfo = absint($instance['showinfo']) ? true : false;
        $width = absint($instance['width']);
        $height = absint($instance['height']);
        $vp = new FAU_Video_Player;
        $vp->create_html($url, $imageurl, $width, $height, $showtitle, $showinfo);
 
        echo $after_widget;
    }

}

   
       