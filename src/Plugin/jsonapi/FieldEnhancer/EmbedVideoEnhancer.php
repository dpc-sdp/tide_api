<?php

namespace Drupal\tide_api\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enhancer with alteration for node path alias.
 *
 * @ResourceFieldEnhancer(
 *   id = "embed_video_enhancer",
 *   label = @Translation("Video Embed field ()"),
 *   description = @Translation("Enhancer with alteration for video embed
 *   fields.")
 * )
 */
class EmbedVideoEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    if (!empty($data)) {
      try {
        $embeddable_url = $this->parseVideos($data);
        $data = $embeddable_url;
      }
      catch (\Exception $exception) {
        // Malformed URL, does nothing.
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($value, Context $context) {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'type' => 'string',
    ];
  }

  /**
   * Helper function to convert video urls to embeddable URLs.
   *
   * @param string $video
   *   The string value of the video URL.
   *
   * @return string
   *   The embeddable url.
   */
  public function parseVideos($video = NULL) {

    // Check for iframe to get the video url.
    if (strpos($video, 'iframe') !== FALSE) {
      // Retrieve the video url.
      $anchorRegex = '/src="(.*)?"/isU';
      $results = [];
      if (preg_match($anchorRegex, $video, $results)) {
        $link = trim($results[1]);
      }
    }
    else {
      // We already have a url.
      $link = $video;
    }

    // If we have a URL, parse it down.
    if (!empty($link)) {
      $video_id = NULL;
      $videoIdRegex = NULL;
      $results = [];

      // Check for type of youtube link.
      if (strpos($link, 'youtu') !== FALSE) {
        if (strpos($link, 'youtube.com') !== FALSE) {
          $array_url = parse_url($link);
          if ($array_url['path'] == "/watch") {
            // Works on:
            // http://www.youtube.com/watch/?v=VIDEOID
            $videoIdRegex = '/youtube.com\/(?:watch\?v=){1}([a-zA-Z0-9_]+)/';

          }
          else {
            // Works on:
            // http://www.youtube.com/embed/VIDEOID
            // http://www.youtube.com/embed/VIDEOID?modestbranding=1&amp;rel=0
            // http://www.youtube.com/v/VIDEO-ID?fs=1&amp;hl=en_US
            $videoIdRegex = '/youtube.com\/(?:embed|v){1}\/([a-zA-Z0-9_]+)\??/i';
          }

        }
        else {
          if (strpos($link, 'youtu.be') !== FALSE) {
            // Works on:
            // http://youtu.be/daro6K6mym8
            $videoIdRegex = '/youtu.be\/([a-zA-Z0-9_]+)\??/i';
          }
        }

        if ($videoIdRegex !== NULL) {
          if (preg_match($videoIdRegex, $link, $results)) {
            $video_str = '//www.youtube.com/embed/%s?autoplay=0&start=0&rel=0';
            $video_id = $results[1];
          }
        }
      }
      // Handle vimeo videos.
      else {
        if (strpos($video, 'vimeo') !== FALSE) {
          if (strpos($video, 'player.vimeo.com') !== FALSE) {
            // Works on:
            // http://player.vimeo.com/video/37985580?title=0&amp;byline=0&amp;portrait=0
            $videoIdRegex = '/player.vimeo.com\/video\/([0-9]+)\??/i';
          }
          else {
            // Works on:
            // http://vimeo.com/37985580
            $videoIdRegex = '/vimeo.com\/([0-9]+)\??/i';
          }

          if ($videoIdRegex !== NULL) {
            if (preg_match($videoIdRegex, $link, $results)) {
              $video_id = $results[1];

              try {
                $hash = unserialize(file_get_contents("//vimeo.com/api/v2/video/$video_id.php"));
                if (!empty($hash) && is_array($hash)) {
                  $video_str = '//vimeo.com/moogaloop.swf?clip_id=%s';
                }
                else {
                  // Don't use, couldn't find what we need.
                  unset($video_id);
                }
              }
              catch (Exception $e) {
                unset($video_id);
              }
            }
          }
        }
      }

      // Check if we have a video id, if so, add the video metadata.
      if (!empty($video_id)) {
        $video = sprintf($video_str, $video_id);
      }
    }

    // Return parsed video.
    return $video;
  }

}
