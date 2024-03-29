<?php class WP_Deploy_Flow_Puller {
  public function __construct($params)
  {
    $this->params = $params;
  }
  public function commands()
  {
    $commands = array();
    if($this->params['ssh_db_host']) {
      $this->_commands_for_database_import_thru_ssh($commands);
    } else {
      $this->_commands_for_database_import_locally($commands);
    }
    $this->_commands_for_database_dump($commands);
    $commands[]= array('rm dump.sql', true);
    $this->_commands_for_files( $commands );
    // Set local path variable
    //$local_dir = ABSPATH;
    $local_dir = '/var/www/domains/time.ly/http';
    // Deactivate Wordpress HTTPS after successful pull
    $commands[]= array("wp plugin deactivate wordpress-https", true);
    // Deactivate Wordpress SEO plugin
    //$commands[]= array("wp plugin deactivate wordpress-seo", true);
    // Disallow indexing
    $commands[]= array("cd $local_dir && echo -e \"User-Agent: *\nDisallow: /\n\" > robots.txt", true);
    // Notify HipChat after successful pull
    $commands[]= array("/usr/local/bin/notify_deployment_success", true);
    return $commands;
  }
  public function commands_for_files() {
    $commands = array();
    $this->_commands_for_files($commands);
    return $commands;
  }
  protected function _commands_for_files(&$commands) {
                extract( $this->params );
    $dir = wp_upload_dir();
    $dist_path = constant( WP_Deploy_Flow_Command::config_constant( 'path' ) ) . '/';
    $remote_path = $dist_path;
    $local_path = '/var/www/domains/time.ly/http';
    $excludes = array_merge(
      $excludes,
      array(
        '.sass-cache',
        'wp-content/cache',
        'wp-content/_wpremote_backups',
        'wp-config.php',
      )
    );
    if(!$ssh_host) {
       // in case the source env is in a subfolder of the destination env, we exclude the relative path to the source to avoid infinite loop
      $remote_local_path = realpath($local_path);
      if($remote_local_path) {
        $remote_path = realpath($remote_path);
        $remote_local_path = str_replace($remote_path . '/', '', $remote_local_path);
        $excludes[]= $remote_locale_path;
      }
    }
    $excludes = array_reduce( $excludes, function($acc, $value) { $acc.= "--exclude \"$value\" "; return $acc; } );
    if ( $ssh_host ) {
      $commands[]= array("rsync -avz --delete -e 'ssh -p $ssh_port' $ssh_user@$ssh_host:$remote_path $local_path $excludes", true);
    } else {
      $commands[]= array("rsync -avz --delete $remote_path $local_path $excludes", true);
    }
  }
  protected function _commands_for_database_import_thru_ssh(&$commands)
  {
                extract( $this->params );
    $host = $db_host . ':' . $db_port;
    $dist_path = constant( WP_Deploy_Flow_Command::config_constant( 'path' ) ) . '/';
    $commands[]= array("ssh $ssh_user@$ssh_host -p $ssh_port \"cd $dist_path;wp db export dump.sql;\"", true);
    $commands[]= array("scp -P $ssh_port $ssh_user@$ssh_host:$dist_path/dump.sql .", true);
    $commands[]= array("ssh $ssh_user@$ssh_host -p $ssh_port \"cd $dist_path; rm dump.sql;\"", true);
  }
  protected function _commands_for_database_import_locally(&$commands)
  {
                extract( $this->params );
    $host = $db_host . ':' . $db_port;
    $wpdb = new wpdb( $db_user, $db_password, $db_name, $host );
    $path = '/var/www/domains/time.ly/http';
    $url = get_bloginfo( 'url' );
    $dist_path = constant( WP_Deploy_Flow_Command::config_constant( 'path' ) ) . '/';
    $commands[]= array("wp migrate to $path $url dump.sql", true);
  }
  protected function _commands_for_database_dump(&$commands) {
                extract( $this->params );
    $commands[]= array('wp db export db_bk.sql', true);
    $commands[]= array('wp db import dump.sql', true);
    //$siteurl = get_option( 'siteurl' );
    $siteurl = 'http://tmstaging.time.ly';
    $searchreplaces = array($url => $siteurl, untrailingslashit( $path ) => untrailingslashit( '/var/www/domains/time.ly/http' ));
    foreach($searchreplaces as $search => $replace) {
      $commands[]= array( "wp search-replace $search $replace", true );
    }
  }
}
