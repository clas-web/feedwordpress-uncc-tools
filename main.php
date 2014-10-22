<?php
/*
Plugin Name: FeedWordPress: UNCC Tools
Plugin URI: 
Description: Adds a UNCC Tools page to the admin menu.  When the tool is run it will correct the following problems: removing duplicate posts, and regenerating post excerpts.
Version: 1.0
Author: Crystal Barton
Author URI: http://clas-pages.uncc.edu/
*/



//----------------------------------------------------------------------------------------
// Add the UNCC Tools admin page.
//----------------------------------------------------------------------------------------
add_action('admin_menu', 'fwp_uncc_tools_plugin_menu');  
function fwp_uncc_tools_plugin_menu() 
{
    add_management_page(
        'FeedWordPress: UNCC Tools',  // title to be displayed.
        'FeedWordPress',              // text to be displayed for this menu item.  
        'administrator',              // type of users can see this menu item.  
        'fwp_uncc_tools_tools',             // unique ID / slug for this menu item.
        'fwp_uncc_tools_display'            // function used to rendering the page for this menu.
    );
}



//----------------------------------------------------------------------------------------
// Displaying the UNCC Tools admin page.
//----------------------------------------------------------------------------------------
function fwp_uncc_tools_display()
{
	if( isset($_GET) && isset($_GET['action']) )
		fwp_uncc_tools_display_results_page();
	else
		fwp_uncc_tools_display_default_page();
}



//----------------------------------------------------------------------------------------
// Show the default admin page.
//----------------------------------------------------------------------------------------
function fwp_uncc_tools_display_default_page()
{
	?>
	
	<div class="wrap">
	
		<h2>FeedWordPress: UNCC Tools</h2>
		
		<h3>Content to Excerpt</h3>
		<p class="description">description goes here.</p>
		<form name="tools" action="tools.php?page=<?php echo $_GET['page']; ?>" method="get">
		
			<input type="hidden" name="page" value="fwp_uncc_tools_tools" />
			<input type="hidden" name="action" value="content-to-excerpt" />
			<input type="checkbox" name="log-file" value="yes" id="log-file" />&nbsp;<label for="log-file">Create log file</label><br/>
			<input type="checkbox" name="display-log" value="yes" id="display-log" />&nbsp;<label for="display-log">Display log</label><br/>
			<input type="submit" value="Begin" />
		
		</form>

		<h3>Remove Duplicates</h3>
		<p class="description">description goes here.</p>
		<form name="tools" action="tools.php?page=<?php echo $_GET['page']; ?>" method="get">
		
			<input type="hidden" name="page" value="fwp_uncc_tools_tools" />
			<input type="hidden" name="action" value="remove-duplicates" />
			<input type="checkbox" name="log-file" value="yes" id="log-file" />&nbsp;<label for="log-file">Create log file</label><br/>
			<input type="checkbox" name="display-log" value="yes" id="display-log" />&nbsp;<label for="display-log">Display log</label><br/>
			<input type="submit" value="Begin" />
		
		</form>
	
	</div>
	
	<?php
}



//----------------------------------------------------------------------------------------
// Show the results admin page.
//----------------------------------------------------------------------------------------
function fwp_uncc_tools_display_results_page()
{
	switch( $_GET['action'] )
	{
		case( 'content-to-excerpt' ):
			fwp_uncc_tools_content_to_excerpt();
			break;
			
		case( 'remove-duplicates' ):
			fwp_uncc_tools_remove_duplicates();
			break;
			
		default:
			echo 'Invalid action: '.$_GET['action'];
			break;
	}
}



//----------------------------------------------------------------------------------------
// Create new excerpts for all posts.
//----------------------------------------------------------------------------------------
function fwp_uncc_tools_content_to_excerpt()
{
	$posts = get_posts('numberposts=-1');
	foreach( $posts as $post )
	{
		$p = array();
	    $p['ID'] = $post->ID;
	    $p['post_excerpt'] = fwp_uncc_tools_create_excerpt( $post->ID, $post->post_title, $post->post_content );
		wp_update_post($p);
	}
}



//----------------------------------------------------------------------------------------
// Remove duplicate posts from site.
//----------------------------------------------------------------------------------------
function fwp_uncc_tools_remove_duplicates()
{
	global $wpdb;
	$log = '';
	
	$args = array(
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
		'post_type'   => 'post',
		'cache_results' => false
	);
	$posts = get_posts( $args );
	
	$log .=  "SUMMARY (".count($posts)." posts)\n";
	$log .= "-------\n\n";

	for( $i = 0; $i < count($posts); $i++ )
	{
		if( empty($posts[$i]) ) continue;
	
		$post = $posts[$i];
		$id = $post->ID;
		$title = $post->post_title;

		$log .= "POST -> ".$id." - ".$title.".\n";
	
		// find all matching posts
		$matches = array( $i );
		for($p = $i + 1; $p < count($posts); $p++ )
		{
			if( ! $posts[$p] ) continue;
		
			if( $posts[$p]->post_title == $title )
			{
				array_push( $matches, $p );
			}
			else
			{
				break;
			}
		}

		if( count($matches) === 1 )
		{
			$log .= "Only one match found.\n\n";
			continue;
		}
		
		$guid_match = array();
		foreach( $matches as $match )
		{
			if( ! $posts[$match] ) continue;
		
			$mid = $posts[$match]->ID;
			if( get_post_meta( $mid, 'guid', TRUE ) )
				array_push( $guid_match, $match );
		}
	
		if( count($guid_match) > 0 )
		{
			$keep_index = $guid_match[0];
			$log .= "GUID found -> ".$posts[$keep_index]->ID." - ".$posts[$keep_index]->post_title."\n";
		}
		else
		{
			$keep_index = $i;
			$log .= "No GUID found. Taking first post. -> ".$posts[$keep_index]->ID." - ".$posts[$keep_index]->post_title."\n";
		}

		foreach( $matches as $match )
		{
			if( $match !== $keep_index )
			{
				$mid = $posts[$match]->ID;
				$mtitle = $posts[$match]->post_title;

				$log .= "Removing -> ".$mid." - ".$mtitle."\n";
				wp_delete_post( $mid, TRUE );
				$posts[$match] = null;
				
				if( $match == $i ) $i--;
			}
		}
		
		$log .= "\n";
	}
	
	$args = array(
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
		'post_type'   => 'post',
		'cache_results' => false
	);
	$posts = get_posts( $args );
	
	$log .= "\n\n\n\nRESULTS (".count($posts)." posts)\n";
	$log .= "-------\n";
	for( $i = 0; $i < count($posts); $i++ )
	{
		if( ! $posts[$i] ) continue;
	
		$post = $posts[$i];
		$id = $post->ID;
		$title = $post->post_title;
		$log .= $id." - ".$title.".\n";
	}
	
	if( isset($_GET['log-file']) )
		file_put_contents( dirname(__FILE__)."/remove-duplicates.txt", $log, FILE_APPEND );
	
	if( isset($_GET['display-log']) )
		echo str_replace( "\n", '<br/>', htmlspecialchars($log) );
	else
		echo 'no log output';
}



//----------------------------------------------------------------------------------------
// Create a new excerpt from the content.
// * Copied and altered from Advanced Excerpt plugin
//----------------------------------------------------------------------------------------
function fwp_uncc_tools_create_excerpt( $id, $title, $content )
{
	$log = "\n\n\n".$id." - ".$title."\n";
	
	$allowed_tags = array(
		'p',
		'b',
		'i',
		'u',
		'strong',
		'em',
		'br',
		'blockquote',
		'pre',
		'code'
	);

	// From the default wp_trim_excerpt():
	// Some kind of precaution against malformed CDATA in RSS feeds I suppose
	$content = str_replace(']]>', ']]&gt;', $content);
	
	$search = array('@<script[^>]*?>.*?</script>@si',  // Strip out javascript
	               '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly 
	               '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments including CDATA 
	);
	$content = preg_replace($search, '', $content); 

    // Strip HTML if allow-all is not set
	if (!in_array('_all', $allowed_tags))
	{
		if (count($allowed_tags) > 0)
			$tag_string = '<' . implode('><', $allowed_tags) . '>';
		else
			$tag_string = '';
		$content = strip_tags($content, $tag_string);
	}
      
	$tokens = array();
	$out = '';
	$w = 0;
	$length = 400;
	$finish_sentence = FALSE;
	$finish_word = TRUE;
      
	// Divide the string into tokens; HTML tags, or words, followed by any whitespace
    // (<[^>]+>|[^<>\s]+\s*)
    preg_match_all('/(<[^>]+>|[^<>\s]+)\s*/u', $content, $tokens);

	// Parse each token
	foreach ($tokens[0] as $t)
	{
		// Limit reached
		if ($w >= $length && !$finish_sentence)
		{
			break;
		}
		
        // Token is not a tag
        if ($t[0] != '<')
        {
        	// Limit reached, continue until ? . or ! occur at the end
        	if ($w >= $length && $finish_sentence && preg_match('/[\?\.\!]\s*$/uS', $t) == 1)
			{
				$out .= trim($t);
				break;
			}
          
			if (1 == $use_words)
			{ // Count words
				$w++;
			}
			else
			{ // Count/trim characters
				$chars = trim($t); // Remove surrounding space
				$c = strlen($chars);
				if ($c + $w > $length && !$finish_sentence)
				{ // Token is too long
					$c = ($finish_word) ? $c : $length - $w; // Keep token to finish word
					$t = substr($t, 0, $c);
				}
				$w += $c;
			}
		}

		$out .= $t;
	}

	$delete_tags = array(
		'br', 'div', 'blockquote', 'p'
	);

	$log .= "Last Character -> ".substr($out, strlen($out)-1, 1);

	while( substr($out, strlen($out)-1, 1) === '>' )
	{
		$start = strrpos($out, '<');
		$tag = substr($out, $start);
		
		$log .= ", Tag -> ".$tag;

		foreach( $delete_tags as $delete_tag )
		{
			if( (strpos($tag, '<'.$delete_tag) === 0) || (strpos($tag, '</'.$delete_tag) === 0) )
			{
				$out = substr($out, 0, $start);
				$log .= " (deleted)";
				break;
			}
		}
	}
	
	$log .= "\n";
	
    $out = trim($out).'...';
    $out = force_balance_tags($out);

	$log .= $out."\n";
    
	if( isset($_GET['log-file']) )
		file_put_contents( dirname(__FILE__)."/content-to-excerpt.txt", $log, FILE_APPEND );
	
	if( isset($_GET['display-log']) )
		echo str_replace( "\n", '<br/>', htmlspecialchars($log) );
	
		
	return $out;
}
