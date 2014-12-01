<?php

namespace PHPemTwitterAPI;

require_once dirname(__FILE__) . '/vendor/tmhOAuth.php';

use Bolt\BaseExtension;

class Extension extends BaseExtension
{
    /**
     * @return array
     */
    public function info()
    {
        $data = array(
            'name' => "PHPemTwitterAPI",
            'description' => "Twitter API integration for the PHPem website",
            'author' => "Jack Bentley (Viva IT)",
            'link' => "http://www.vivait.co.uk",
            'version' => "1.0",
            'required_bolt_version' => "1.5",
            'highest_bolt_version' => "2.0",
            'type' => "Twig function",
            'first_releasedate' => "0000-00-00",
            'latest_releasedate' => "0000-00-00",
            'allow_in_user_content' => true,
        );

        return $data;
    }

    public function initialize()
    {
        $this->app['twig.loader.filesystem']->addPath(__DIR__);

        $this->addTwigFunction('latestTweets', 'latestTweets');
    }

    public function latestTweets($user, $amount = 1)
    {

        $api = new \tmhOAuth([
            'consumer_key'    => $this->config['auth']['consumer']['key'],
            'consumer_secret' => $this->config['auth']['consumer']['secret'],
            'token'           => $this->config['auth']['access']['token'],
            'secret'          => $this->config['auth']['access']['secret'],
            //'bearer'          => 'YOUR_OAUTH2_TOKEN',
            'user_agent'      => 'PHPem Website',
        ]);

        $api->apponly_request([
            'url' => $api->url('1.1/statuses/user_timeline'),
            'without_bearer' => true,
            'params' => [
                'screen_name' => $user,
                'count' => $amount,
            ]
        ]);

        $response = json_decode($api->response['response'], true);

        $output = '';

        foreach($response as $tweet) {
            $this->embedTweetLinks($tweet);

            if(isset($tweet['retweeted_status']))
                $this->embedTweetLinks($tweet['retweeted_status']);

            $output .= $this->app['render']->render($this->config['template']['tweet'], $tweet);
        }

        return new \Twig_Markup($output, 'UTF-8');
    }

    private function embedTweetLinks(&$tweet) {
        $tweet['text'] = htmlentities($tweet['text']);
        if(isset($tweet['entities']['user_mentions'])) {
            foreach(array_reverse($tweet['entities']['user_mentions']) as $entity) {
                $tweet['text'] = substr_replace(
                    $tweet['text'],
                    '<a href="https://www.twitter.com/' . urlencode($entity['screen_name']) . '" target="_blank">'
                    . substr($tweet['text'], $entity['indices'][0], $entity['indices'][1] - $entity['indices'][0])
                    . '</a>',
                    $entity['indices'][0], $entity['indices'][1] - $entity['indices'][0]
                );
            }
        }

        if(isset($tweet['entities']['hashtags'])) {
            foreach(array_reverse($tweet['entities']['hashtags']) as $entity) {
                $tweet['text'] = str_replace(
                    '#' . $entity['text'],
                    '<a href="https://www.twitter.com/hashtag/' . urlencode($entity['text']) . '" target="_blank">'
                    . '#' . $entity['text']
                    . '</a>',
                    $tweet['text']
                );
            }
        }

        if(isset($tweet['entities']['media'])) {
            foreach(array_reverse($tweet['entities']['media']) as $entity) {
                $tweet['text'] = str_replace(
                    $entity['url'],
                    '<a href="' . $entity['url'] . '" target="_blank">'
                    . $entity['display_url']
                    . '</a>',
                    $tweet['text']
                );
            }
        }

        if(isset($tweet['entities']['urls'])) {
            foreach(array_reverse($tweet['entities']['urls']) as $entity) {
                $tweet['text'] = str_replace(
                    $entity['url'],
                    '<a href="' . $entity['url'] . '" target="_blank">'
                    . $entity['display_url']
                    . '</a>',
                    $tweet['text']
                );
            }
        }
    }
}