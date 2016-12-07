<?php namespace Boparaiamrit\Twitter;


use Illuminate\Support\ServiceProvider;

class TwitterServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->mergeConfigFrom(__DIR__ . '/../config/twitter.php', 'twitter');
		
		$this->publishes([
			__DIR__ . '/../config/twitter.php' => config_path('twitter.php'),
		]);
	}
	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton('twitter', function ($app) {
			return new TwitterClient($app['config'], $app['session.store'], $app['bugsnag']);
		});
	}
	
	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return ['twitter'];
	}
	
}