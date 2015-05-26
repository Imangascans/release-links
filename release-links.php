<?php
/*
  Plugin Name: Release Links
  Plugin URI: https://github.com/Imangascans/release-links
  Description: Provides the ability to add and display links below posts
  Version: 1.0
  Author: Georgi Kostadinov
  Author URI: https://imangascans.org/author/georgi/
  License: MIT

  Copyright(c) 2015 Georgi Kostadinov (email : georgi@imangascans.org)
 
  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in
  all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
  THE SOFTWARE.
*/

class ReleaseLinksPlugin {
  protected $action;
  protected static $nonce_key = 'release-links-nonce';
  protected static $textdomain = 'release-links-textdomain';
  protected static $name_prefix = 'release-link-';
  protected static $default_types = array(
    array('type' => 'DDL', 'url' => ''),
  );
    
  public function __construct() {
    $this->action = plugin_basename( __FILE__ );
    add_filter( 'admin_init', array($this,'add_post_box') );
    add_filter( 'edit_post', array($this,'save_new_release') );
    add_filter( 'the_content', array($this,'display_links'), 20 );
    add_filter( 'get_the_excerpt', array($this,'display_links_excerpt'), 20 );
  }
  
  function display_links ($content, $override=false) {
    global $post;
    if (!$override && (is_search() || is_feed())) return $content;
    $theLinks = $this->getLinks($post->ID);
    if (count($theLinks) == 0) return $content;
    
    $links 	= '<div class="links">';
    $links .= '<span class="links">Links:</span>';
    $links .= '<ul>';
    
    foreach ($theLinks as $link) {
      if (!$link['url']) continue;
      $links .= '<li><a class="release_link" href="'.esc_url($link['url']).'" target="_blank">'.$link['type'].'</a></li>';
    }
    $links .= '</ul></div>';

    return $content . $links;
  }
  
  function display_links_excerpt ($content) {
    return ($this->display_links($content, true));
  }
  
  function add_post_box() {
    add_meta_box(
      'release-links',
      __( 'Release Links', static::$textdomain ),
      array($this,'form_post'),
      'post',
      'normal',
      'default'
    );
  }
  public function form_post($post, $metabox) {
    wp_nonce_field( $this->action, static::$nonce_key );

    $p = wp_is_post_revision($post->ID);
    if ( ! $p ) $p = $post->ID;
    $theLinks = $this->getLinks($p);
    if (count($theLinks) == 0)
      $theLinks = static::$default_types;

    foreach ($theLinks as $link) {
      $name = static::$name_prefix . $link['type'];
      echo "<label for='$name'>";
      _e($link['type'], static::$textdomain );
      echo '</label>&nbsp;';
      echo "<input type='text' name='$name' value='{$link['url']}' size='80' /><br />";
    }
  }
  
  function save_new_release( $post_id ) {      
    global $post;
    // verify if this is an auto save routine.
    // If it is our form has not been submitted, so we don't want to do anything
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return;

    // verify this came from the our screen and with proper authorization,
    // because save_post can be triggered at other times
    if ( ! isset($_POST[static::$nonce_key])) return;

    if ( ! wp_verify_nonce($_POST[static::$nonce_key], $this->action) ) return;

    // Check permissions
    if ( 'page' == $_POST['post_type'] )
    {
      if ( !current_user_can( 'edit_page', $post->ID ) )
        return;
    }
    else
    {
      if ( !current_user_can( 'edit_post', $post->ID ) )
        return;
    }   
    $p = wp_is_post_revision($post->ID);
    if ( ! $p ) $p = $post->ID;
    $theLinks = $this->getLinks($p);     
    
    if (count($theLinks) == 0) 
      $theLinks = static::$default_types;
    
    foreach ($theLinks as &$link) {
      $name = static::$name_prefix . $link['type'];
      if (!isset($_POST[$name])) continue;
      $url = $_POST[$name];
      if ($url != $link['url']) $link['url'] = $url;
    }  
    
    $this->setLinks($p, $theLinks); 
  }
  
  public function getLinks($postId) {
    global $wpdb;
    $query = "SELECT type, url 
    FROM {$wpdb->prefix}release_links
    WHERE newsID = %d";
    $q = $wpdb->prepare($query, $postId);
    $r = $wpdb->get_results($q, ARRAY_A);
    return $r;
  }
  
  public function setLinks($postId, $links) {
    global $wpdb;
    
    $oldLinks = $this->getLinks($postId);
    $hasType = function($type) use($oldLinks) {
      foreach ($oldLinks as $l) {
        if ($l['type'] == $type) return true;
      }
      return false;
    };
    
    foreach ($links as $link) {
      if (!$link['url']) continue;
      if (!$hasType($link['type'])) {
        $wpdb->insert($wpdb->prefix . 'release_links', 
        array(
          'newsID' => $postId, 
          'type' => $link['type'], 
          'url' => $link['url']
        ), array('%d', '%s', '%s'));
      }
      else {
        $wpdb->update($wpdb->prefix . 'release_links', 
        array(
          'url' => $link['url']
        ), 
        array(
          'newsID' => $postId, 
          'type' => $link['type']
        ), array('%s'), array('%d', '%s'));
      }
    }    
  }
  
  public function install() {
    global $wpdb;
    $wpdb->query(
"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}release_link_types` (
  `ID` INT NOT NULL AUTO_INCREMENT ,
  `Type` VARCHAR(20) NOT NULL ,
PRIMARY KEY  (`ID`),
UNIQUE INDEX `Type` (`Type` ASC));");
    $wpdb->query(
"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}release_links` (
  `ID` INT NOT NULL AUTO_INCREMENT ,
  `newsID` INT NOT NULL ,
  `type` VARCHAR(20) NOT NULL ,
  `url` TEXT NOT NULL ,
  PRIMARY KEY  (`ID`) ,
  UNIQUE INDEX `NewsID` (`newsID` ASC, `type` ASC) ,
  CONSTRAINT `TypeFK`
    FOREIGN KEY (`type` )
    REFERENCES `{$wpdb->prefix}release_link_types` (`Type` )
    ON DELETE CASCADE
    ON UPDATE NO ACTION);");
  
    $types = static::$default_types;
    foreach ($types as $t) {
      $wpdb->insert($wpdb->prefix . 'release_link_types', 
        array('Type' => $t['type']), array('%s'));
    }
  }
}
$release_links = new ReleaseLinksPlugin();
register_activation_hook(__FILE__, array(&$release_links,'install'));
