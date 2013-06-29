<?php
/* 
Plugin Name: Twitter Tweets
Description: Display Latest Tweets from a User (API 1.1).
Version: 0.5.0
Author: ...a few good HipHoppers...
Author URI: http://www.5ivedesign.co.za
License: GPL2
	Copyright 2013  5ive Design Studio (Pty) Ltd.
	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License,
	version 2, as published by the Free Software Foundation.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

#-----------------------------------------------------------------
# PLUGIN MANAGEMENT
#
# @author This class is built and maintained by Joachim Kudish
#-----------------------------------------------------------------
require_once 'updater.php';

if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin
    $config = array(
        'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
        'proper_folder_name' => 'twitter-tweets', // this is the name of the folder your plugin lives in
        'api_url' => 'https://api.github.com/repos/5iDS/twitter-tweets', // the github API url of your github repo
        'raw_url' => 'https://raw.github.com/5iDS/twitter-tweets/master', // the github raw url of your github repo
        'github_url' => 'https://github.com/5iDS/twitter-tweets', // the github url of your github repo
        'zip_url' => 'https://github.com/5iDS/twitter-tweets/zipball/master', // the zip url of the github repo
        'sslverify' => true, // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
        'requires' => '3.3', // which version of WordPress does your plugin require?
        'tested' => '3.3', // which version of WordPress is your plugin tested up to?
        'readme' => 'README.md', // which file to use as the readme for the version number
        'access_token' => '' // Access private repositories by authorizing under Appearance > Github Updates when this example plugin is installed
    );
    new WP_GitHub_Updater($config);
}

#-----------------------------------------------------------------
# require the twitter auth class
#-----------------------------------------------------------------
require_once 'twitter/twitteroauth.php';

#-----------------------------------------------------------------
# LEGGO!
#-----------------------------------------------------------------
class myTweets {
	
	//ATTRIBUTES
	/**
	private $_twitter_consumerKey;
	private $_twitter_consumerSecret;
	private $_twitter_accessToken;
	private $_twitter_accessTokenSecret;
	/**/
	private $_twitter_consumerKey= 'w6iNaoLkWY9vitTrYfNx2Q';
	private $_twitter_consumerSecret = '2LjxehNV9LtLuNMuymwoSftvyEtH5RDIWYjCKpt0';
	private $_twitter_accessToken= '304531337-hnzwljWH3LNhJUATVTEbex04EsqccX5vnGFuzkKY';
	private $_twitter_accessTokenSecret= 't9yJ2yN38UVuiEicJLwUgSYaZoVrxzohbVMA9Y9zqE';
	/**/
	
	public function __contruct($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret) {
		/**
		$this->_twitter_consumerKey = $consumerKey;
    	$this->_twitter_consumerSecret = $consumerSecret;
		$this->_twitter_accessToken = $accessToken;
    	$this->_twitter_accessTokenSecret = $accessTokenSecret;
		/**/
	}

	#-----------------------------------------------------------------
	#	GET TWEETS & CACHE EM IN A WP Transient
	#	@param string username (with the @!)
	#	@param int number of tweets to return
	#	@param int cacheTime of transient
	#	@return object Tweets
	#-----------------------------------------------------------------
	public function getTweets($screenName,$tweetCount,$cacheTime) {
		
		if(empty($screenName) || empty($tweetCount) || empty($cacheTime)) {
			throw new Exception('Please ensure you\'ve provided a user name, number of tweets as well as length of cache.');
		}

		$transName = 'fids_list_tweets';
		// Get any existing copy of our transient data
		if ( false === ( $twitterData = get_transient( $transName ) ) ) {
			
			$twitterConnection = new TwitterOAuth(
				$this->_twitter_consumerKey,
    			$this->_twitter_consumerSecret,
				$this->_twitter_accessToken,
    			$this->_twitter_accessTokenSecret
			);
			
			/* If HTTP response is 200 continue otherwise throw a fit! */

			$twitterData = $twitterConnection->get(
				'statuses/user_timeline',
				array(
					'screen_name'     => $screenName,
					'count'           => $tweetCount,
					'exclude_replies' => false
				)
			);
     		// Save our new transient.
			set_transient($transName, $twitterData, 60 * $cacheTime);
			/**/
     		
			/**
			if( $twitterConnection->http_code != 200 ) {
				$tweets = get_transient($transName);
			}
			/**/
		}

		$tweets = get_transient($transName);

		foreach ($tweets as $tweet) {
			$msg = $this->linkify($tweet->text);
			$msg = $this->twitterize($msg);
			$msg = $this->encode_tweet($msg);
			$msgs[] = $msg;
			/**
			$permalink = 'http://twitter.com/#!/@'. stripslashes(of_get_option('twitter', '')) .'/status/'. $tweet->id_str;
			$time = strtotime($tweet->created_at);
			if ( ( abs( time() - $time) ) < 86400 ) {
				$h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
			} else {
				$h_time = date(__('Y/m/d'), $time);
			}; 
			/**/
		}
		return $msgs;
	}
	
	#-----------------------------------------------------------------
	#	Find links and create the hyperlinks
	#	@param string text to find hyperlinks
	#	@return string with anchors linkified
	#-----------------------------------------------------------------
	private function linkify($text) {
	    $text = preg_replace('/\b([a-zA-Z]+:\/\/[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"$1\" class=\"twitter-link\" target=\"_blank\" rel=\"nofollow\">$1</a>", $text);
		$text = preg_replace('/\b(?<!:\/\/)(www\.[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"http://$1\" class=\"twitter-link\" target=\"_blank\">$1</a>", $text);
		// match name@address
		$text = preg_replace("/\b([a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]*\@[a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]{2,6})\b/i","<a href=\"mailto://$1\" class=\"twitter-link\" rel=\"nofollow\">$1</a>", $text);
		//match #trendingtopics. Props to Michael Voigt
		$text = preg_replace('/([\.|\,|\:|\¡|\¿|\>|\{|\(]?)#{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/#search?q=$2\" class=\"twitter-link\" target=\"_blank\" rel=\"nofollow\">#$2</a>$3 ", $text);
		return $text;
	}

	#-----------------------------------------------------------------
	#	Find twitter usernames and link to them
	#	@param string text to find @name
	#	@return string with @usernames linkified
	#-----------------------------------------------------------------
	private function twitterize($text) {
		$text = preg_replace('/([\.|\,|\:|\¡|\¿|\>|\{|\(]?)@{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/$2\" class=\"twitter-user\" target=\"_blank\">@$2</a>$3 ", $text);
		return $text;
	}

	#-----------------------------------------------------------------
	#	Encode single quotes in tweets
	#	@param string
	#	@return string
	#-----------------------------------------------------------------
	private function encode_tweet($text) {
		$text = mb_convert_encoding( $text, "HTML-ENTITIES", "UTF-8");
		return $text;
	}
}
?>