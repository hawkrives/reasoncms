<?php
reason_include_once( 'minisite_templates/modules/default.php' );
$GLOBALS[ '_module_class_names' ][ basename( __FILE__, '.php' ) ] = 'WebcamsModule';

class WebcamsModule extends DefaultMinisiteModule
{
  function init( $args = array() )
  {

  }
  
  function has_content()
  {
    return true;
  }

  function run()
  {
        echo 'Caf Cam';
	echo '<a href="https://reasondev.luther.edu/x/webcam/"><img src="https://reasondev.luther.edu/images/luther2010/webcams/caf_cam_link.png"/></a>';
  }
}
?>
