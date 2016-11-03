<?php namespace Boparaiamrit\Twitter;


use Abraham\TwitterOAuth\TwitterOAuth;
use Abraham\TwitterOAuth\TwitterOAuthException;
use Abraham\TwitterOAuth\Util\JsonDecoder;
use Boparaiamrit\Twitter\Traits\AccountTrait;
use Boparaiamrit\Twitter\Traits\BlockTrait;
use Boparaiamrit\Twitter\Traits\DirectMessageTrait;
use Boparaiamrit\Twitter\Traits\FavoriteTrait;
use Boparaiamrit\Twitter\Traits\FriendshipTrait;
use Boparaiamrit\Twitter\Traits\GeoTrait;
use Boparaiamrit\Twitter\Traits\HelpTrait;
use Boparaiamrit\Twitter\Traits\ListTrait;
use Boparaiamrit\Twitter\Traits\MediaTrait;
use Boparaiamrit\Twitter\Traits\SearchTrait;
use Boparaiamrit\Twitter\Traits\StatusTrait;
use Boparaiamrit\Twitter\Traits\TrendTrait;
use Boparaiamrit\Twitter\Traits\UserTrait;
use Bugsnag\Client;
use Carbon\Carbon;
use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Session\Store as SessionStore;

class TwitterClient extends TwitterOAuth implements ITwitterClient
{
	
	use AccountTrait, BlockTrait, DirectMessageTrait, FavoriteTrait, FriendshipTrait,
		GeoTrait, HelpTrait, ListTrait, MediaTrait, SearchTrait, StatusTrait, TrendTrait, UserTrait;
	
	private $config;
	private $Session;
	private $Logger;
	
	public function __construct(Config $Config, SessionStore $Session, Client $Logger)
	{
		if ($Config->get('twitter')) {
			$this->config = $Config->get('twitter');
		} else {
			throw new Exception('No config found');
		}
		
		$this->Session = $Session;
		$this->Logger  = $Logger;
		
		$accessToken       = $this->config[ self::ACCESS_TOKEN ];
		$accessTokenSecret = $this->config[ self::ACCESS_TOKEN_SECRET ];
		
		if ($Session->has('twitter.ouath_token') && $Session->has('twitter.ouath_token_secret')) {
			$accessToken       = $this->Session->get('twitter.ouath_token');
			$accessTokenSecret = $this->Session->get('twitter.ouath_token_secret');
		}
		
		parent::__construct($this->config[ self::CONSUMER_KEY ], $this->config[ self::CONSUMER_SECRET ], $accessToken, $accessTokenSecret);
	}
	
	/**
	 * @param string $oauthCallback
	 *
	 * @param bool   $signInWithTwitter
	 *
	 * @return string
	 */
	public function getAuthorizationURL($oauthCallback = '', $signInWithTwitter = true)
	{
		$parameters = [];
		if (!empty($oauthCallback)) {
			$parameters['oauth_callback'] = $oauthCallback;
		} else {
			$parameters['oauth_callback'] = $this->config[ self::OUATH_CALLBACK_URL ];
		}
		
		try {
			$response = $this->oauth($this->config[ self::REQUEST_TOKEN_ROUTE ], $parameters);
			
			if (!array_has($response, 'oauth_token') && !array_has($response, 'oauth_token_secret')) {
				throw new TwitterOAuthException('No Token Received');
			}
			
			$ouathToken = array_get($response, 'oauth_token');
			
			$this->Session->set('twitter.ouath_request_token', $ouathToken);
			$this->Session->set('twitter.ouath_request_token_secret', array_get($response, 'oauth_token_secret'));
			
			if ($signInWithTwitter) {
				return $this->url($this->config[ self::AUTHENTICATE_ROUTE ], ['oauth_token' => $ouathToken]);
			} else {
				return $this->url($this->config[ self::AUTHORIZE_ROUTE ], ['oauth_token' => $ouathToken]);
			}
		} catch (TwitterOAuthException $Exception) {
			$message = JsonDecoder::decode($Exception->getMessage(), true);
			
			$expiredToken = false;
			foreach ($message['errors'] as $error) {
				if ($error['code'] == 89) {
					$expiredToken = true;
				}
			}
			
			if ($expiredToken) {
				$this->Session->forget('twitter.ouath_token');
				$this->Session->forget('twitter.ouath_token_secret');
			}
			
			$this->Logger->notifyException($Exception);
		}
		
		return '';
	}
	
	/**
	 * @param string $ouathToken
	 * @param string $oauthVerifier
	 * @param bool   $saveToSettings
	 *
	 * @return bool
	 */
	public function getAccessToken($ouathToken, $oauthVerifier, $saveToSettings = false)
	{
		$parameters = [];
		if (!empty($oauthVerifier)) {
			$parameters['oauth_verifier'] = $oauthVerifier;
		}
		
		try {
			// Reset Token
			$this->setOauthToken($ouathToken, $this->Session->get('twitter.ouath_request_token_secret'));
			
			// Get new Token
			$response = $this->oauth($this->config[ self::ACCESS_TOKEN_ROUTE ], $parameters);
			
			$ouathToken       = array_get($response, 'oauth_token');
			$ouathTokenSecret = array_get($response, 'oauth_token_secret');
			
			$this->Session->set('twitter.ouath_token', $ouathToken);
			$this->Session->set('twitter.ouath_token_secret', $ouathTokenSecret);
			
			// Reset Token
			$this->setOauthToken($ouathToken, $ouathTokenSecret);
			
			if ($saveToSettings) {
				settings()->set('twitter.ouath_token', $ouathToken);
				settings()->set('twitter.ouath_token_secret', $ouathTokenSecret);
			}
			
			return true;
		} catch (TwitterOAuthException $Exception) {
			$this->Logger->notifyException($Exception);
			
		}
		
		return false;
	}
	
	public function linkify($tweet)
	{
		$text = '';
		if (is_object($tweet)) {
			$type  = 'object';
			$tweet = $this->jsonDecode(json_encode($tweet), true);
		} else if (is_array($tweet)) {
			$type = 'array';
		} else {
			$type = 'text';
			$text = ' ' . $tweet;
		}
		
		$patterns             = [];
		$patterns['url']      = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
		$patterns['mailto']   = '([_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3}))';
		$patterns['user']     = ' +@([a-z0-9_]*)?';
		$patterns['hashtag']  = '(?:(?<=\s)|^)#(\w*[\p{L}-\d\p{Cyrillic}\d]+\w*)';
		$patterns['long_url'] = '>(([[:alnum:]]+:\/\/)|www\.)?([^[:space:]]{12,22})([^[:space:]]*)([^[:space:]]{12,22})([[:alnum:]#?\/&=])<';
		
		if ($type == 'text') {
			// URL
//			$pattern = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
			$text = preg_replace_callback('#' . $patterns['url'] . '#i', function ($matches) {
				$input = $matches[0];
				$url   = preg_match('!^https?://!i', $input) ? $input : "http://$input";
				
				return '<a href="' . $url . '" target="_blank" rel="nofollow">' . "$input</a>";
			}, $text);
		} else {
			$text     = $tweet['text'];
			$entities = $tweet['entities'];
			
			$search  = [];
			$replace = [];
			
			if (array_key_exists('media', $entities)) {
				foreach ($entities['media'] as $media) {
					$search[]  = $media['url'];
					$replace[] = '<a href="' . $media['media_url_https'] . '" target="_blank">' . $media['display_url'] . '</a>';
				}
			}
			
			if (array_key_exists('urls', $entities)) {
				foreach ($entities['urls'] as $url) {
					$search[]  = $url['url'];
					$replace[] = '<a href="' . $url['expanded_url'] . '" target="_blank" rel="nofollow">' . $url['display_url'] . '</a>';
				}
			}
			
			$text = str_replace($search, $replace, $text);
		}
		
		// Mailto
		$text = preg_replace('/' . $patterns['mailto'] . '/i', "<a href=\"mailto:\\1\">\\1</a>", $text);
		
		// User
		$text = preg_replace('/' . $patterns['user'] . '/i', " <a href=\"https://twitter.com/\\1\" target=\"_blank\">@\\1</a>", $text);
		
		// Hashtag
		$text = preg_replace('/' . $patterns['hashtag'] . '/ui', "<a href=\"https://twitter.com/search?q=%23\\1\" target=\"_blank\">#\\1</a>", $text);
		
		// Long URL
		$text = preg_replace('/' . $patterns['long_url'] . '/', ">\\3...\\5\\6<", $text);
		
		// Remove multiple spaces
		$text = preg_replace('/\s+/', ' ', $text);
		
		return trim($text);
	}
	
	public function ago($timestamp)
	{
		if (is_numeric($timestamp) && (int)$timestamp == $timestamp) {
			$carbon = Carbon::createFromTimestamp($timestamp);
		} else {
			$dt     = new \DateTime($timestamp);
			$carbon = Carbon::instance($dt);
		}
		
		return $carbon->diffForHumans();
	}
	
	public function linkUser($user)
	{
		return 'https://twitter.com/' . (is_object($user) ? $user->screen_name : $user);
	}
	
	public function linkTweet($tweet)
	{
		return $this->linkUser($tweet->user) . '/status/' . $tweet->id_str;
	}
	
	public function linkRetweet($tweet)
	{
		return 'https://twitter.com/intent/retweet?tweet_id=' . $tweet->id_str;
	}
	
	public function linkAddTweetToFavorites($tweet)
	{
		return 'https://twitter.com/intent/favorite?tweet_id=' . $tweet->id_str;
	}
	
	public function linkReply($tweet)
	{
		return 'https://twitter.com/intent/tweet?in_reply_to=' . $tweet->id_str;
	}
	
	private function jsonDecode($json, $assoc = false)
	{
		if (version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
			return json_decode($json, $assoc, 512, JSON_BIGINT_AS_STRING);
		} else {
			return json_decode($json, $assoc);
		}
	}
	
	public function setCustomerToken()
	{
		if (settings()->hasKey('twitter.ouath_token') && settings()->hasKey('twitter.ouath_token_secret')) {
			$this->setOauthToken(settings('twitter.ouath_token'), settings('twitter.ouath_token_secret'));
		}
	}
}
