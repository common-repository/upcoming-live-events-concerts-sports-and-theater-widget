<?php
	/*
	Plugin Name: Rukkus Concerts, Sports, and Theater - Content Tagger
	Plugin URI: http://www.rukkus.com
	Description: Find upcoming live concerts, sports, and theater events/shows by the performers in your content
	Version: 1.3
	Author: Manick Bhan
	Author URI: http://www.rukkus.com
	*/

	$css_stylesheet_name = 'rukkus-content-tagger.css';
	
	if( !class_exists( 'WP_Http' ) )
    	include_once( ABSPATH . WPINC. '/class-http.php' );

	function rukkus_build_html_from_data($data){
		//print_r($data);
		$all_events_html=" ";

		foreach ($data as $e){
			$performer_names='';
			$performer_headshot=$e['performers'][0]['headshot_url'];
			$performer_headshot = str_replace(".png", "_240.png", $performer_headshot);
			$default_headshot = '/images/home/missingimg4.png';
			$performer_headshot = !empty($performer_headshot) ? $performer_headshot : $default_headshot;
			$event_url_stub=$e['url_stub'].'?ref=wp-ct';
			$performer_url_stub=$e['performers'][0]['url_stub'].'?ref=wp-ct';
			$venue_url_stub=$e['venue']['url_stub'].'?ref=wp-ct';
			$venue_name=$e['venue']['name'];
			$venue_city=$e['venue']['city'];
			$venue_state=$e['venue']['state'];
			$event_id=$e['id'];
			$event_time_local=$e['event_time_local'];
			
			
			$event_time_string = date('D|M d|g:ia', strtotime($event_time_local));
			$x=explode('|',$event_time_string);
			$weekday=$x[0];
			$monthday=$x[1];
			$time=$x[2];

			$performers=$e['performers'];
			$minprice_string=isset($e['minprice'])?'from $'.round($e['minprice']):null;
			$count=0;
			$total=count($performers);
			foreach ($performers as $p){
				$count+=1;
				if($count==1){
					$performer_names .= $p['name'];
				}elseif($count==$total){
					$performer_names .= ' and '. $p['name'];
				}else{
					$performer_names .= ', '. $p['name'];
				}
			}

			$event_html='<tbody>
				<tr>
					<td>
						<a href="http://rukkus.com/'.$performer_url_stub.'">
							<img class="performer-headshot" width="45" alt="'.$performer_names.' tickets" src="http://static.rukkus.com'.$performer_headshot.'">
						</a>
					</td>
					<td class="event_item_date">
						<span class="editable-date">
							<meta itemprop="startDate" content="event_time_local">
								<span class="event_item_date_weekday">'.$weekday.'</span>
								<br>
								<span class="event_item_date_day">'.$monthday.'</span>
								<br>
								<span class="editable-text event_item_date_time">'.$time.'&nbsp;</span>
						</span>
					</td>
					<td>
						<a itemprop="url" target="_blank" href="http://rukkus.com/'.$event_url_stub.'">
							<h4 itemprop="name" class="event-name">
								'.$performer_names.'
							</h4>
						</a>
						<div itemscope="" itemprop="location" itemtype="http://schema.org/Place" class="event_item_venue ellipsis rukkus_event_item_&lt;%= id %&gt;">
							<a itemprop="url" target="_blank" href="http://rukkus.com/venue/'.$venue_url_stub.'" class="ellipsis">
								<h6 itemprop="name" class="editable-text event_item_venue_name">'.$venue_name.'</h6>
							</a>
							<div itemprop="address" itemscope="" itemtype="http://schema.org/PostalAddress" class="rukkus-venue-location">
								<h6 itemprop="addressLocality" class="editable-text event_item_venue_city ellipsis">'.$venue_city.'</h6>
								<h6 itemprop="addressRegion" class="editable-text event_item_venue_state ellipsis">'.$venue_state.'</h6>
							</div>
						</div>
					</td>
					<td itemprop="offers" itemscope="" itemtype="http://schema.org/AggregateOffer" title="Click to see all available tickets" class="event_item_buy tooltip">
						<a itemprop="url" target="_blank" class="buy_tickets_link" href="http://rukkus.com/'.$event_url_stub.'">
							<article class="shadow attachment rukkus-event_item_buy_button">TICKETS</article>
						</a>
						<h6 itemprop="lowPrice" class="event_item_buy_tickets">'.$minprice_string.'</h6>
					</td>
				</tr>
			</tbody>';
			$all_events_html.=$event_html;			
		}

		$events_table_html='<table class="headerTable">
				<tr class="header-box">
					<h2 class="header-text">Shows Near You</h2>
				</tr>
			</table><table class="eventsTable"> '.$all_events_html.' </table>';

		return $events_table_html;
	}

	function rukkus_load_css(){
		wp_register_style('rukkus-content-tagger-css', plugins_url('rukkus-content-tagger.css', __FILE__) );
		wp_enqueue_style('rukkus-content-tagger-css');
	}

	function rukkus_tag_content($content) {
		if (strpos(get_the_content('^&amp;^&amp;'), '^&amp;^&amp;') > 0){
			return $content;
		}

		$api_url = 'http://tickets.rukkus.com/contenttag/';
		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null ;
		$browser = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
		
		$content_purified_ascii = preg_replace('/[^(\x20-\x7F)]*/','', $content);
		$page_url=get_permalink();
		$post_title=get_the_title();

		$body = array(
			'url' => $page_url,
			'user_ip' => $ip,
			'browser' => $browser,
			'text' => $content_purified_ascii,
			'title' => $post_title
		);
		$begin_tag='<!-- BEGIN Rukkus.com Content Tagger -->';
		$events_html='';
		$end_tag='<!-- END Rukkus.com Content Tagger -->';

		try {
			$request = new WP_Http;
			$result = $request->request( $api_url, array( 'method' => 'POST', 'body' => $body) );
			
			if ( isset($result['body']) ) {
				$resp=$result['body'];
				$data=json_decode($resp,true);
				if( isset($data) && count($data) ){
					$events_html=rukkus_build_html_from_data($data);
				}
			}
		} catch (Exception $e) {
			//$error = 'Caught exception: ',  $e->getMessage(), "\n";
		}
			
		return $content.$begin_tag.$events_html.$end_tag;
		return $items;
	}
?>

<?php
	add_filter('init','rukkus_load_css');
	add_filter('the_content','rukkus_tag_content' );
?>
