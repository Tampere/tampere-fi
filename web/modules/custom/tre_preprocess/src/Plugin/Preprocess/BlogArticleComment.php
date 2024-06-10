<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\user\UserInterface;

/**
 * Blog article comment preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.comment__field_comment__blog_article",
 *   hook = "comment__field_comment__blog_article"
 * )
 */
class BlogArticleComment extends TrePreProcessPluginBase {

  /**
   * The field whose value to use as the comment author for logged-in users.
   */
  const COMMENT_AUTHOR_FIELD = 'field_blog_post_author';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $comment = $variables['comment'];

    $comment_author = $comment->getOwner();
    if (!($comment_author instanceof UserInterface)) {
      return $variables;
    }

    $is_verified_user = FALSE;
    if (!$comment_author->isAnonymous()) {
      $is_verified_user = TRUE;
    }

    $variables['is_verified_user'] = $is_verified_user;

    $blog_article = $variables['commented_entity'];
    $blog_article_has_author_field_value = $blog_article->hasField(self::COMMENT_AUTHOR_FIELD) && !$blog_article->get(self::COMMENT_AUTHOR_FIELD)->isEmpty();

    if (!$is_verified_user || !$blog_article_has_author_field_value) {
      $comment_author_display_name = $comment_author->getDisplayName();
    }
    else {
      $comment_author_display_name = $blog_article->get(self::COMMENT_AUTHOR_FIELD)->getString();
    }

    $variables['comment_author_display_name'] = $comment_author_display_name;

    return $variables;
  }

}
