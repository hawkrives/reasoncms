<?php 
reason_include_once( 'minisite_templates/modules/events.php' );
reason_include_once( 'classes/calendar.php' );
reason_include_once( 'classes/calendar_grid.php' );
reason_include_once( 'classes/icalendar.php' );
include_once( CARL_UTIL_INC . 'dir_service/directory.php' );
$GLOBALS[ '_module_class_names' ][ basename( __FILE__, '.php' ) ] = 'LutherEventsModule';

class LutherEventsModule extends EventsModule
{
	var $list_date_format = 'l, F j';
	
	//////////////////////////////////////
	// For The Events Listing
	//////////////////////////////////////
	function show_event_details() // {{{
	{
		$e =& $this->event;
		echo '<div class="eventDetails">'."\n";
		$this->show_back_link();
		$this->show_images($e);
		echo '<h3>'.$e->get_value('name').'</h3>'."\n";
		$this->show_ownership_info($e);
		if ($e->get_value('description'))
			echo '<p class="description">'.$e->get_value( 'description' ).'</p>'."\n";

		if ($e->get_value('content'))
			echo '<div class="eventContent">'.$e->get_value( 'content' ).'</div>'."\n";
		$this->show_repetition_info($e);
		if (!empty($this->request['date']) && strstr($e->get_value('dates'), $this->request['date']))
			echo '<p class="date"><strong>Date:</strong> '.prettify_mysql_datetime( $this->request['date'], "l, F j, Y" ).'</p>'."\n";
		if(substr($e->get_value( 'datetime' ), 11) != '00:00:00')
			echo '<p class="time"><strong>Time:</strong> '.prettify_mysql_datetime( $e->get_value( 'datetime' ), "g:i a" ).'</p>'."\n";
		$this->show_duration($e);
		if ($e->get_value('location'))
			echo '<p class="location"><strong>Location:</strong> '.$e->get_value('location').'</p>'."\n";
		if ($e->get_value('sponsor'))
			echo '<p class="sponsor"><strong>Sponsored by:</strong> '.$e->get_value('sponsor').'</p>'."\n";
		$this->show_contact_info($e);
		if($this->show_icalendar_links)
			$this->show_item_export_link($e);
		$this->show_dates($e);
		if ($e->get_value('url'))
			echo '<div class="eventUrl"><strong>For more information, visit:</strong> <a href="'.$e->get_value( 'url' ).'">'.$e->get_value( 'url' ).'</a>.</div>'."\n";
		//$this->show_back_link();
		$this->show_event_categories($e);
		$this->show_event_audiences($e);
		$this->show_event_keywords($e);
		echo '</div>'."\n";
	} // }}}
	
	function show_repetition_info(&$e) // {{{
	{
		$rpt = $e->get_value('recurrence');
		$freq = '';
		$words = array();
		$dates_text = '';
		$occurence_days = array();
		if (!($rpt == 'none' || empty($rpt)))
		{
			$words = array('daily'=>array('singular'=>'day','plural'=>'days'),
							'weekly'=>array('singular'=>'week','plural'=>'weeks'),
							'monthly'=>array('singular'=>'month','plural'=>'months'),
							'yearly'=>array('singular'=>'year','plural'=>'years'),
					);
			if ($e->get_value('frequency') <= 1)
				$sp = 'singular';
			else
			{
				$sp = 'plural';
				$freq = $e->get_value('frequency').' ';
			}
			if ($rpt == 'weekly')
			{
				$days_of_week = array('sunday','monday','tuesday','wednesday','thursday','friday','saturday');
				foreach($days_of_week as $day)
				{
					if($e->get_value($day))
						$occurence_days[] = $day;
				}
				$last_day = array_pop($occurence_days);
				$dates_text = ' on ';
				if (!empty( $occurence_days ) )
				{
					$comma = '';
					if(count($occurence_days) > 2)
						$comma = ',';
					$dates_text .= ucwords(implode(', ', $occurence_days)).$comma.' and ';
				}
				$dates_text .= prettify_string($last_day);
			}
			elseif ($rpt == 'monthly')
			{
				$suffix = array(1=>'st',2=>'nd',3=>'rd',4=>'th',5=>'th');
				if ($e->get_value('week_of_month'))
				{
					$dates_text = ' on the '.$e->get_value('week_of_month');
					$dates_text .= $suffix[$e->get_value('week_of_month')];
					$dates_text .= ' '.$e->get_value('month_day_of_week');
				}
				else
					$dates_text = ' on the '.prettify_mysql_datetime($e->get_value('datetime'), 'j').' day of the month';
			}
			elseif ($rpt == 'yearly')
			{
				$dates_text = ' on '.prettify_mysql_datetime($e->get_value('datetime'), 'F j');
			}
			echo '<p class="repetition">This event takes place each ';
			echo $freq;
			echo $words[$rpt][$sp];
			echo $dates_text;
			echo ' from '.prettify_mysql_datetime($e->get_value('datetime'), 'F j, Y').' to '.prettify_mysql_datetime($e->get_value('last_occurence'), 'F j, Y').'.';
			
			echo '</p>'."\n";
		}
			
	} // }}}
	function show_dates(&$e)
	{
		$dates = explode(', ', $e->get_value('dates'));
		if(count($dates) > 1 || empty($this->request['date']) || !strstr($e->get_value('dates'), $this->request['date']))
		{
			echo '<div class="dates"><h4>This event occurs on:</h4>'."\n";
			echo '<ul>'."\n";
			foreach($dates as $date)
			{
				echo '<li>'.prettify_mysql_datetime( $date, "l, F j, Y" ).'</li>'."\n";
			}
			echo '</ul>'."\n";
			echo '</div>'."\n";
		}
	}
}
?>
