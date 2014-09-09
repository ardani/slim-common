<?php
namespace Collegeman\Slim\Common;

class TwigExtension extends \Twig_Extension {

  public function getName() {
    return 'common';
  }

  public function getFunctions() {
    return array(
      new \Twig_SimpleFunction('styleLinkTags', array($this, 'styleLinkTags')),
      new \Twig_SimpleFunction('scriptTags', array($this, 'scriptTags')),
      new \Twig_SimpleFunction('csrf', 'csrf'),
      new \Twig_SimpleFunction('current_user_can', 'current_user_can'),
    );
  }

  public function styleLinkTags($styles = null) {
    if (!empty($styles)) {
      foreach($styles as $style) {
        if (substr($style, 0, 4) === 'http') {
          $url = $style;
        } else {
          $url = substr($style, 0, 1) === '/' ? $style.'.css' : '/css/' . $style . '.css';
        }
        ?>
          <link href="<?= $url ?>" rel="stylesheet">
        <?php
      }
    }
  }

  public function scriptTags($scripts = null) {
    if (!empty($scripts)) {
      foreach($scripts as $script) {
        if (strpos($script, 'http') !== false || strpos($script, '//') === 0) {
          $url = $script;
        } else {
          $url = strpos($script, '/') === 0 ? $script.'.js' : '/js/' . $script . '.js';
        }
        ?>
          <script src="<?= $url ?>"></script>
        <?php
      }
    }
  }

}