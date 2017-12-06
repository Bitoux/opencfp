<?php

declare(strict_types=1);

/**
 * Copyright (c) 2013-2017 OpenCFP
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/opencfp/opencfp
 */

namespace OpenCFP\Provider;

use Aptoma\Twig\Extension\MarkdownEngine;
use Aptoma\Twig\Extension\MarkdownExtension;
use OpenCFP\Application;
use OpenCFP\Domain\CallForPapers;
use OpenCFP\Domain\Services\Authentication;
use OpenCFP\Http\View\TalkHelper;
use OpenCFP\Infrastructure\Event\TwigGlobalsListener;
use OpenCFP\Infrastructure\Templating\TwigExtension;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\EventListenerProviderInterface;
use Silex\Provider\TwigServiceProvider as SilexTwigServiceProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig_Environment;
use Twig_Extension_Debug;

class TwigServiceProvider implements ServiceProviderInterface, EventListenerProviderInterface
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $c)
    {
        $c->register(new SilexTwigServiceProvider(), [
            'twig.path'    => $this->app['path']->templatesPath(),
            'twig.options' => [
                'debug' => !$this->app['env']->isProduction(),
                'cache' => $this->app->config('cache.enabled') ? $this->app['path']->cacheTwigPath() : false,
            ],
        ]);

        $c->extend('twig', function (Twig_Environment $twig, Application $app) {
            if (!$app['env']->isProduction()) {
                $twig->addExtension(new Twig_Extension_Debug());
            }

            $twig->addExtension(new TwigExtension(
                $app['request_stack'],
                $app['url_generator']
            ));

            $twig->addGlobal('site', $app->config('application'));

            // Twig Markdown Extension
            $engine = new MarkdownEngine\MichelfMarkdownEngine();
            $twig->addExtension(new MarkdownExtension($engine));

            $twig->addExtension(new \Twig_Extensions_Extension_Text());

            $twig->addGlobal(
                'talkHelper',
                $this->app[TalkHelper::class]
            );

            return $twig;
        });
    }

    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber(new TwigGlobalsListener(
            $app[Authentication::class],
            $app[CallForPapers::class],
            $app['session'],
            $app['twig']
        ));
    }
}
