<?php
/**
 * Plugin Name: FAU Video-Player
 * Description: Shortcode für Videos vom Videoportal
 * Version: 1.5.0
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
register_deactivation_hook(__FILE__, array('FAU_Video_Player', 'deactivate'));

class FAU_Video_Player {

    const version = '1.5.0';
    const option_name = '_fau_video_player';
    const version_option_name = '_fau_video_player_version';
    const textdomain = 'fau-video-player';
    const php_version = '5.5'; // Minimal erforderliche PHP-Version
    const wp_version = '4.6'; // Minimal erforderliche WordPress-Version

    protected $embedscript = false;
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

        add_action('widgets_init', create_function('', 'return register_widget("FAUVideoWidget");'));

     

        //  add_action('init', array($this, 'register_script'));
        //  add_action('wp_footer', array($this, 'print_script'));
    }

    public function register_script() {
        wp_register_script('fauvideo', plugins_url('/', __FILE__) . 'js/jwplayer.js', false, self::version, true);
    }

    public function displayscript($show = false) {
        $this->embedscript = $show;
    }

    public function print_script() {
        if ($this->embedscript == true) {
            wp_enqueue_script('fauvideo');
        }
        return;
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
		    $this->displayscript(true);
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
		    
		    if ($showinfo==true) {
			$showtitle = true;
		    }
		    $output .= '<div class="fauvideo-'.$rand.'" itemscope itemtype ="http://schema.org/Movie">';
		    if (isset($video['title'])) {
			$output .= "<$titletag itemprop=\"name\"";
			if ($showtitle ==false) {
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
		    // $loading = __('Video wird geladen...', self::textdomain);
		 
                    echo $output;
                    
                    echo do_shortcode('[video preload="none" width="' . $width . '" height="' . $height . '" src="' . $file . '" poster="' . $image . '"][/video]');
                    
                     $output='';
		    
		    if ($showinfo==true) {
			 $output .= "<ul class=\"info\">\n";
			 if (isset($video['author_name'])) {
			    $output .= '<li>'.__('Autor',self::textdomain).': <span class="actor">'.$video['author_name'].'</span></li>'."\n";
			 }
			 $output .= '<li>'.__('Quelle',self::textdomain).': <a href="'.$url.'" class="isBasedOnUrl">'.$url.'</a></li>'."\n";
			 if (isset($video['provider_name'])) {
			    $output .= '<li>'.__('Copyright',self::textdomain).': <a href="'.$video['provider_url'].'"><span class="publisher">'.$video['provider_name'].'</span></a></li>'."\n";
			 }
			 $output .= "</ul>\n";
		    }
		    $output .= "</div>\n";
		   
                    echo $output;
		} else {
		    echo __('Die angegebene URL lieferte keine Videodaten. Bitte verwenden Sie die Adresse aus dem Videoportal, in dem ein Video als solches auch angezeigt wird und keine Indexseite.', self::textdomain);
		}
		
		
		return $output;
            } else {
                echo __('Es können nur Videos vom Videoportal eingebunden werden.', self::textdomain);
            }
	    
	} else {
	    echo __('Fehlerhafte URL',self::textdomain);
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

}

class FAUVideoWidget extends WP_Widget {

    function __construct() {
        $widget_ops = array('classname' => 'FAUVideoWidget', 'description' => __('Video aus dem Videoportal einbinden', 'fau-video-player'));
        parent::__construct('FAUVideoWidget', 'FAU Videoportal', $widget_ops);
    }

    function form($instance) {

        if ($instance) {
            $title = esc_attr($instance['title']);
            $url = esc_url($instance['url']);
            $showtitle = intval($instance['showtitle']);
            $showinfo = intval($instance['showinfo']);
            $width = intval($instance['width']);
            $height = intval($instance['height']);
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
        echo '<label for="' . $this->get_field_id('title') . '">' . __('Titel', 'fau-video-player') . ': </label>';
        echo '<input type="text" id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" value="' . $title . '">';
        echo '</p>';
        echo '<p>';
        echo '<label for="' . $this->get_field_id('url') . '">' . __('Video-URL', 'fau-video-player') . ': </label>';
        echo '<input size="40" type="text" id="' . $this->get_field_id('url') . '" name="' . $this->get_field_name('url') . '" value="' . $url . '">';
        echo '</p>';

        echo '<p>';
        echo '<label for="' . $this->get_field_id('width') . '">' . __('Breite', 'fau-video-player') . ': </label>';
        echo '<input  size="4" type="text" id="' . $this->get_field_id('width') . '" name="' . $this->get_field_name('width') . '" value="' . $width . '">';
        echo '</p>';
        echo '<p>';
        echo '<label for="' . $this->get_field_id('height') . '">' . __('Höhe', 'fau-video-player') . ': </label>';
        echo '<input size="4" type="text" id="' . $this->get_field_id('height') . '" name="' . $this->get_field_name('height') . '" value="' . $height . '">';
        echo '</p>';
        echo '<p>';
        echo '<label for="' . $this->get_field_id('imageurl') . '">' . __('Vorschaubild-URL', 'fau-video-player') . ': </label>';
        echo '<input size="40" type="text" id="' . $this->get_field_id('imageurl') . '" name="' . $this->get_field_name('imageurl') . '" value="' . $imageurl . '">';
        echo '</p>';
        ?>
        <p>
            <select class="onoff" name="<?php echo $this->get_field_name('showtitle'); ?>" id="<?php echo $this->get_field_id('showtitle'); ?>">
                <option value="0" <?php selected(0, $showtitle); ?>>Aus</option>
                <option value="1" <?php selected(1, $showtitle); ?>>An</option>
            </select>
            <label for="<?php echo $this->get_field_id('showtitle'); ?>">
                <?php echo __('Zeige auch Videotitel', 'fau-video-player'); ?>
            </label>
        </p>	
        <p>
            <select class="onoff" name="<?php echo $this->get_field_name('showinfo'); ?>" id="<?php echo $this->get_field_id('showinfo'); ?>">
                <option value="0" <?php selected(0, $showinfo); ?>>Aus</option>
                <option value="1" <?php selected(1, $showinfo); ?>>An</option>
            </select>
            <label for="<?php echo $this->get_field_id('showinfo'); ?>">
                <?php echo __('Zeige Metainformationen und Videotitel', 'fau-video-player'); ?>
            </label>
        </p>
        <?php
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['url'] = esc_url($new_instance['url']);
        $instance['imageurl'] = esc_url($new_instance['imageurl']);
        $instance['showinfo'] = intval($new_instance['showinfo']);
        $instance['showtitle'] = intval($new_instance['showtitle']);
        $instance['width'] = intval($new_instance['width']);
        $instance['height'] = intval($new_instance['height']);
        return $instance;
    }

    function widget($args, $instance) {
        extract($args, EXTR_SKIP);

        echo $before_widget;

        if (!empty($instance['title']))
            echo '<h2 class="small">' . $instance['title'] . '</h2>';

        $url = esc_url($instance['url']);
        $imageurl = esc_url($instance['imageurl']);
        $showtitle = intval($instance['showtitle']);
        $showinfo = intval($instance['showinfo']);
        $width = intval($instance['width']);
        $height = intval($instance['height']);
        $vp = new FAU_Video_Player;
        //$vp->displayscript(true);  
        //	add_action('init', array($vp, 'register_script'));
        //	add_action('wp_footer', array($vp, 'print_script'));
      
         
        
  $vp->create_html($url, $imageurl, $width, $height, $showtitle, $showinfo);
 

        echo $after_widget;
    }

}

   
       