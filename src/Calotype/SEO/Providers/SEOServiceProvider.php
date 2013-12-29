<?php namespace Calotype\SEO\Providers;

use Calotype\SEO\Generators\MetaGenerator;
use Calotype\SEO\Generators\OpenGraphGenerator;
use Calotype\SEO\Generators\RobotsGenerator;
use Calotype\SEO\Generators\SitemapGenerator;
use Illuminate\Http\Response;

use Illuminate\Support\ServiceProvider;

class SEOServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['config']->package('calotype/seo', __DIR__ . '/../../../config');

        $this->registerBindings();
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $app = $this->app;

        // Create the default robots.txt content
        $this->app['calotype.seo.generators.robots']->addUserAgent('*');
        $this->app['calotype.seo.generators.robots']->addDisallow('');
        $this->app['calotype.seo.generators.robots']->addSpacer();
        $this->app['calotype.seo.generators.robots']->addSitemap($this->app['request']->root() . '/sitemap.xml');

        // Generate sitemap.xml route
        $this->app['router']->get(
            'sitemap.xml',
            function () use ($app) {
                if ($app['config']->get('seo::sitemap.cache')):
                    $sitemap = $app['cache']->remember(
                        'seo::sitemap.xml',
                        $app['config']->get('seo::sitemap.cachetime'),
                        function () use ($app) {
                            $app['calotype.seo.generators.sitemap.run']->run();
                            return $app['calotype.seo.generators.sitemap']->generate();
                        }
                    );
                else:
                    $app['calotype.seo.generators.sitemap.run']->run();
                    $sitemap = $app['calotype.seo.generators.sitemap']->generate();
                endif;

                $response = new Response($sitemap, 200);
                $response->header('Content-Type', 'text/xml');

                return $response;
            }
        );

        // Generate robots.txt route
        $this->app['router']->get(
            'robots.txt',
            function () use ($app) {
                $response = new Response($app['calotype.seo.generators.robots']->generate(), 200);
                $response->header('Content-Type', 'text/plain');

                return $response;
            }
        );
    }

    /**
     * Register the bindings.
     *
     * @return void
     */
    public function registerBindings()
    {
        // Register the sitemap.xml generator
        $this->app->singleton(
            'calotype.seo.generators.sitemap',
            function ($app) {
                return new SitemapGenerator();
            }
        );

        // Register the sitemap configuration
        $this->app->singleton(
            'calotype.seo.generators.sitemap.run',
            function ($app) {
                $class = $app['config']->get('seo::sitemap.classrun');
                return new $class($app['calotype.seo.generators.sitemap']);
            }
        );

        // Register the meta tags generator
        $this->app->singleton(
            'calotype.seo.generators.meta',
            function ($app) {
                return new MetaGenerator($app['config']->get('seo::meta.defaults'));
            }
        );

        // Register the robots.txt generator
        $this->app->singleton(
            'calotype.seo.generators.robots',
            function ($app) {
                return new RobotsGenerator();
            }
        );

        // Register the open graph properties generator
        $this->app->singleton(
            'calotype.seo.generators.opengraph',
            function ($app) {
                return new OpenGraphGenerator();
            }
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'calotype.seo.generators.meta',
            'calotype.seo.generators.sitemap',
            'calotype.seo.generators.robots',
        );
    }

}
