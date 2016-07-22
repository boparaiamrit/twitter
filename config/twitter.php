<?php

use Boparaiamrit\Twitter\ITwitterClient;

return [
	ITwitterClient::CONSUMER_KEY        => env('TWITTER_CONSUMER_KEY', ''),
	ITwitterClient::CONSUMER_SECRET     => env('TWITTER_CONSUMER_SECRET', ''),
	ITwitterClient::ACCESS_TOKEN        => env('TWITTER_ACCESS_TOKEN', ''),
	ITwitterClient::ACCESS_TOKEN_SECRET => env('TWITTER_ACCESS_TOKEN_SECRET', ''),
	ITwitterClient::OUATH_CALLBACK_URL  => env('APP_URL') . '/web/twitter/callback',
	ITwitterClient::AUTHORIZE_ROUTE     => 'oauth2/authorize',
	ITwitterClient::AUTHENTICATE_ROUTE  => 'oauth/authenticate',
	ITwitterClient::ACCESS_TOKEN_ROUTE  => 'oauth/access_token',
	ITwitterClient::REQUEST_TOKEN_ROUTE => 'oauth/request_token'
];
