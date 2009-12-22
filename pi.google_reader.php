<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array( 'pi_name'        => 'Google Reader',
					  'pi_version'     => '1.0',
					  'pi_author'      => 'Curtis Herbert',
					  'pi_author_url'  => 'http://forgottenexpanse.com/projects/code/ee_reader/',
					  'pi_description' => 'Allows you to display your google starred, shared, and unreal items',
					  'pi_usage'       => Google_reader::usage() );

/**
 * Google_reader Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author  		Curtis Herbert <me@forgottenexpanse.com>
 * @url 			http://forgottenexpanse.com/projects/code/ee_reader/
 * @version 		1.0 (2009-12-22)
 */
class Google_reader 
{

	var $return_data;

	//urls needed to interact with google
	var $login_url 			= 	'https://www.google.com/accounts/ClientLogin';
	var $feed_url 			= 	'http://www.google.com/reader/atom/';
	var $token_url			=	'http://www.google.com/reader/api/0/token';
	var $source		 		= 	'Google Reader API for Expression Engine';

	//states for items
	var $starred_state 		= 	'user/-/state/com.google/starred';
	var $shared_state 		= 	'user/-/state/com.google/broadcast';
	var $reading_state		= 	'user/-/state/com.google/reading-list';
	
	//internal variables
	var $_cookie;
	var $_channel;
	var $_token;
	var $_cache_name		= 	'google_reader';
	
	//config items
	var $_id;
	var $_email;
	var $_password;
	var $_limit 			= 	20;
	var $_refresh			= 	15;
	var $_type 				= 	'shared';
	
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
  	function Google_reader() 
  	{	
		$this->_initialize();
		
		try 
		{				
			switch($this->_type) 
			{
				case 'all':
					$this->return_data = $this->_all_items();
					break;
				case 'shared':
					$this->return_data = $this->_shared_items();
					break;
				case 'starred':
					$this->return_data = $this->_starred_items();
					break;
				default:
					throw new Exception('Invalid stream type ' . $this->_type);
					break;
			}		
			
		} 
		catch (Exception $e) 
		{
			$this->_log_error($e->getMessage());
			$this->return_data = '';
		}
	}
	
	/**
	 * Loads the configuration items passed in through the tag along with the actual
	 * template data for formatting.
	 *
	 * @access 	private
	 * @return 	void
	 */
	function _initialize() 
	{
		$this->EE =& get_instance(); 
		$this->data = $this->EE->TMPL->tagdata;
		
		if ($this->EE->TMPL->fetch_param('id')) $this->_id = $this->EE->TMPL->fetch_param('id');
		if ($this->EE->TMPL->fetch_param('email')) $this->_email = $this->EE->TMPL->fetch_param('email');
		if ($this->EE->TMPL->fetch_param('password')) $this->_password = $this->EE->TMPL->fetch_param('password');		
		if ($this->EE->TMPL->fetch_param('limit')) $this->_limit = $this->EE->TMPL->fetch_param('limit');
		if ($this->EE->TMPL->fetch_param('type')) $this->_type = $this->EE->TMPL->fetch_param('type');
		if ($this->EE->TMPL->fetch_param('refresh')) $this->_refresh = $this->EE->TMPL->fetch_param('refresh');
	}
	
	/**
	 * Fetches all the items in a logged in user's stream and returns a formatted
	 * text string of those items using the tags in the template.
	 * 
	 * @access 	private
	 * @return 	string of formatted items from the feed
	 */
	function _all_items() 
	{
		$feed_xml = $this->_all_items_raw();
		$unread_result = $this->_parse_items($feed_xml);
		return $this->_parse_template($unread_result);
	}
	
	/**
	 * Fetches shared the items in a in user's stream and returns a formatted
	 * text string of those items using the tags in the template.
	 *
	 * Unlike the other functions, you do not need to provide an email and 
	 * password to use this function.  Instead you can provide the userid
	 * (string of numbers) for the user as this information is made
	 * available through a public URL.
	 *
	 * To get the id, log into Google Reader, go to your shared items,
	 * and then click "See your shared items page in a new window."  You'll
	 * see the numbers in the URL you are taken to.
	 * 
	 * @access 	private
	 * @return 	string of formatted items from the feed
	 */
	function _shared_items() 
	{				
		$feed_xml = ($this->_login() ? $this->_shared_items_raw() : $this->_shared_items_raw($this->_id));
		$shared_items_result = $this->_parse_items($feed_xml);
		return $this->_parse_template($shared_items_result);
 	}
 	
 	/**
	 * Fetches starred the items in a logged in user's stream and returns a formatted
	 * text string of those items using the tags in the template.
	 * 
	 * @access 	private
	 * @return 	string of formatted items from the feed
	 */
 	function _starred_items() 
 	{				
		$feed_xml = $this->_starred_items_raw($this->_id);
		$starred_items_result = $this->_parse_items($feed_xml);
		return $this->_parse_template($starred_items_result);
 	}
 		
	/**
	 * The XML of all items for the logged in user.
	 * 
	 * @access 	private
	 * @return 	string of raw XML returned from the all items feed
	 */
	function _all_items_raw() 
	{
		if ($this->_login() === FALSE) 
		{
			throw new Exception('Must provide valid login credentials to get a list of unread items');
		}
		
		return $this->_fetch($this->feed_url . $this->reading_state);
	}
	
	/**
	 * The XML of shared items for the logged in user.
	 *
	 * @access 	private
	 * @param 	string $userid (optional) the userid to get the shared items for
	 * @return 	string of raw XML returned from the shared items feed
	 */
	function _shared_items_raw($userid = NULL) 
	{
		if (is_null($userid)) {
			if ($this->_login() === FALSE) 
			{
				throw new Exception('Either email/password or id must be provided to access shared items');
			}
		}
		
		//If they provided a user id to use, switch out the URL to use the public feed instead
		$url = $this->feed_url . $this->shared_state;
		if (!is_null($userid)) 
		{
			$url = str_replace('/atom/user/-/', '/public/atom/user/' . $userid . '/', $this->feed_url . $this->shared_state);
		}
		return $this->_fetch($url);
	}
	
	/**
	 * The XML of starred items for the logged in user.
	 * 
	 * @access 	private
	 * @return 	string of raw XML returned from the starred items feed
	 */
	function _starred_items_raw() 
	{
		if ($this->_login() === FALSE) 
		{
			throw new Exception('Must provide valid login credentials to get a list of starred items');
		}
		
		return $this->_fetch($this->feed_url . $this->starred_state);
	}
	
	/**
	 * Replaces the template tags passed in with items from the reader feed.
	 * 
	 * @access 	private
	 * @return 	string with expression engine tags replaced
	 */
	function _parse_template($result) 
	{
		//if the user wants less items than were returned trim the array
		if ($this->_limit < count($result['items'])) 
		{
			$result['items'] = array_slice($result['items'], 0, $this->_limit);
		}
		
 		return $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, array($result));
  	}
	
	/**
	 * If the user is not logged into Google Reader, this function will log in
	 * using the provided email and password and store off a session ID for later
	 * calls.
	 * 
	 * @access 	private
	 * @return 	TRUE/FALSE based on success of loggin in
	 */
	function _login() {
		if (isset($this->_cookie)) 
		{
			return TRUE;
		}
		
		if (!isset($this->_email) || !isset($this->_password)) 
		{
			return FALSE;
		}
		
		$post_data = array();
		$post_data['Email'] = $this->_email;
		$post_data['Passwd'] = $this->_password;
		$post_data['continue'] = 'http://www.google.com/';
		$post_data['source'] = $this->source;
		$post_data['service'] = 'reader';
		
		$this->_channel = curl_init($this->login_url);
		curl_setopt($this->_channel, CURLOPT_POST, TRUE);
		curl_setopt($this->_channel, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($this->_channel, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($this->_channel, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($this->_channel);
		curl_close($this->_channel);
		
		if (strpos($result, "SID=") === FALSE) 
		{
			throw new Exception('User credentials provided were not valid');
		}
		
		if ($i = strstr($result, "LSID")) 
		{
  			$sid = substr($result, 0, (strlen($result) - strlen($i)));
  			$sid = rtrim(substr($sid, 4, (strlen($sid) - 4)));
  			
  			$this->_cookie = 'SID=' . $sid . '; domain=.google.com; path=/; expires=1600000000';
  			return TRUE;
		}
		else 
		{
			unset($this->_cookie);
			return FALSE;
		}
	}
	
	/**
	 * Gets a token from google to allow editing of data.  This function requires a
	 * logged in user.
	 * 
	 * @access 	private
	 * @return 	TRUE/FALSE based on success of getting token
	 */
	function _load_token() 
	{
		if (!isset($this->_token)) 
		{
			if (isset($this->_cookie)) 
			{
				$token = $this->_fetch($this->token_url);
				if (strpos($token, 'access') !== FALSE) 
				{
					throw new Exception('Unable to get token from google');
				}
				
				$this->_token = $token;
				return TRUE;	
			}
			
			throw new Exception('User must be logged in to get a token');
		}
	}
	
	/**
	 * Helper function to parse an XML string of feed items into an
	 * array containing just the used data for easier consumption.  Returned
	 * array will contain a lastupdated value for the last time the feed
	 * was updated and an items value which is the array of items.
	 * 
	 * @access 	private
	 * @see 	_parse_item
	 * @param 	string $entries_xml XML to parse
	 * @return 	array of items
	 */
	function _parse_items($items_xml) {
		$feed_object = simplexml_load_string($items_xml);
		$results = array();
		$feed_items = array();
		foreach ($feed_object->entry as $item) {		
			$feed_items[] = $this->_parse_item($item);
		}
		$results['lastupdated'] = $this->_get_date($feed_object->updated);
		$feed_object = NULL;
		$results['items'] = $feed_items;
		return $results;
	}
	
	/**
	 * Given a SimpleXML representation of a feed item from Google Reader
	 * this function will parse out the appropriate data into an array for
	 * easier consumption.
	 *
	 * Information returned is title, url, published, updated, and summary.
	 * 
	 * @access 	private
	 * @param 	mixed $entry SimpleXML representation of an entry
	 * @return 	array of data for a feed item
	 */
	function _parse_item($raw_item) {
		$item = array();
		$item['title'] = strval($raw_item->title);
		$item['url'] =  $raw_item->link['href'];
		$item['published'] = $this->_get_date($raw_item->published);
		$item['updated'] = $this->_get_date($raw_item->updated);
		$item['summary'] = strval($raw_item->summary);
		$item['id'] = strval($raw_item->id);
		return $item;
	}
		
	/**
	 * Cleans up the date string returned by Google Reader into a PHP
	 * date-time object.  Expected format is YYYY-MM-DDTHH:MM:SSZ.
	 * 
	 * @access 	private
	 * @param 	string $date date as reported from Google Reader
	 * @return 	void
	 */
	function _get_date($date) {
		return strtotime(str_replace('Z', '', str_replace('T',' ', $date)));
	}
	
	/**
	 * Fetches a URL.  Will use the cookie for login if it is set.
	 * 
	 * @access 	private
	 * @param 	string $url URL to fetch
	 * @param 	array $post post data to include in the request
	 * @return 	string contents of URL
	 */
	function _fetch($url, $post = NULL) {
	
		if (($result = $this->_check_cache($url)) === FALSE)
		{
			$this->_channel = curl_init($url);
			curl_setopt($this->_channel, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($this->_channel, CURLOPT_FOLLOWLOCATION, TRUE);
			if (isset($this->_cookie))
			{
				curl_setopt($this->_channel, CURLOPT_COOKIE, $this->_cookie);
			}
			if (!is_null($post))
			{
				$post_string = '';
				foreach($post as $key=>$value)
				{ 
					$post_string .= $key . '=' . $value . '&'; 
				}
				$post_string = substr($post_string, 0, -1);
				curl_setopt($this->_channel, CURLOPT_POST, TRUE);
				curl_setopt($this->_channel, CURLOPT_POSTFIELDS, $post_string);
			}
			$result = curl_exec($this->_channel);
			
			curl_close($this->_channel);
			
			$this->_write_cache($url, $result);	
		}
		
		return $result;
	}
	
	/**
	 * Checks for cached data
	 *
	 * @access	private
	 * @param	string $url being fetched
	 * @return	string if pulling from cache, FALSE otherwise
	 */
	function _check_cache($url)
	{	
		$cache_directory = APPPATH . 'cache/' . $this->_cache_name . '/';
		
		//check to make sure the file exists
		if (!@is_dir($cache_directory))
		{
			$this->_log_error('Cache directory does not exist');
			return FALSE;
		}
		
        $file = $cache_directory . sha1($url);
		if (!file_exists($file) || !($filestream = @fopen($file, 'rb')))
		{
			$this->_log_error('Unable to open cache file ' . sha1($url));
			return FALSE;
		}
		
		//read in the file
		flock($filestream, LOCK_SH);
		$cache = @fread($filestream, filesize($file));
		$timestamp = filemtime($file);
		flock($filestream, LOCK_UN);
		fclose($filestream);
        		
		if (time() > ($timestamp + ($this->_refresh * 60)))
		{
			return FALSE;
		}
				
        return $cache;
	}

	/**
	 * Writes data to the cache under a given URL
	 *
	 * @access	private
	 * @param	string $url url to store the data under
	 * @param	string $data xml to store in the cache
	 * @return	void
	 */
	function _write_cache($url, $data)
	{	
		$cache_directory = APPPATH . 'cache/' . $this->_cache_name . '/';

		//make sure we can write to the cache directory
		if (!@is_dir($cache_directory))
		{
			if (!@mkdir($cache_directory))
			{
				$this->_log_error('Unable to create cache directory');
				return FALSE;
			}
			@chmod($cache_directory, 0777);	     
		}
		
		$file = $cache_directory . sha1($url);
	
		if (!($filestream = @fopen($file, 'wb')))
		{
			$this->_log_error('Unable to write to cache file ' . sha1($url));
			return FALSE;
		}
		
		flock($filestream, LOCK_EX);
		fwrite($filestream, $data);
		flock($filestream, LOCK_UN);
		fclose($filestream);
        
		@chmod($file, 0777);		
	}
	
	/**
	 * Logs an error to the Expressionengine logs.
	 *
	 * @access	private
	 * @param 	string error message to log
	 * @return	void
	 */
	function _log_error($message)
	{
		$this->EE->TMPL->log_item('Google Reader Error: ' . $message);
	}
	
	/**
	 * Plugin Usage, called by Expressionengine control panel to display usage.
	 *
	 * @access	public
	 * @return	string usage information
	 */
	function usage()
	{
		ob_start(); 
		?>
		
		The Google Reader plugin allows you to display your shared, starred, and unread items from google reader within your site.
		
		This plugin requires that you have an account with google reader.
		
		***************************
		Examples
		***************************
		
		Link to the 10 most recent shared items:
		
			{exp:google_reader id='12006118737470781753' limit='10'}
			<div class="date">Updated on {lastupdated format="%F %d, %Y"}</div>
			<ul id="links">
			{items}
			<li><a href="{url}">{title}</a></li>
			{/items}
			</li></ul>
			{/exp:google_reader}
		
		Display 20 starred items with summaries:
		
			{exp:google_reader email='someone@someisp.com' password='arealpassword' limit='20' type='starred'}
			<div class="date">Updated on {lastupdated format="%F %d, %Y"}</div>
			<ul id="links">
			{items}
			<li>
				<a href="{url}">{title}</a>
				<p>{summary}</p>
			</li>
			{/items}
			</li></ul>
			{/exp:google_reader}
			
		***************************
		Parameters
		***************************	
		
		type:		Type of items to display. Valid values are "shared", "starred", or "all".
		
		limit:		Maximum number of items to display. Default is 20.
			
		refresh		How many minutes to cache responses for. Default is 15 minutes.
		
		email:		Email address of user to show items for. Required for types "starred" and "all".
			
		password:	Password of user to show items for. Required for types "starred" and "all".
		
		id:			The public ID of the google user. This can only be used with shared items as it is the only feed that is publicly accessible. To get the id, log into Google Reader, go to your shared items, and then click "See your shared items page in a new window."  You'll see a string of numbers in the URL you are taken to.
			
		***************************
		Single Variables
		***************************
		
		{lastupdated}:	Last updated date for the entire feed.
		
		***************************
		Pair Variables
		***************************
		
		{item}:	Represents a single item within the feed.
		
				Single variables available within an item:
				
				{title}:		Title of the feed item.
					
				{url}:			URL to this feed item.
				
				{published}:	Publish date for the feed item.
				
				{updated}:		Last updated date for the feed item.
				
				{summary}:		Summary for the feed item.

		***************************
		Change Log
		***************************
		
		Version 1.0 (2009-12-22):
			- Initial release
		
		<?php
		$buffer = ob_get_contents();
		
		ob_end_clean(); 
		
		return $buffer;
	}
}

/* End of file pi.google_reader.php */
/* Location: ./system/expressionengine/third_party/google_reader/pi.google_reader.php */ 
