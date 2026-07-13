<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<?php
function threadedComments($comments, $options) {
    // 缓存评论作者，用于在深层回复中显示 @提及
    static $commentAuthors = [];
    $commentAuthors[$comments->coid] = $comments->author;

    // 超过此层级后不再缩进，改用 @提及 保留上下文
    $maxVisualDepth = 3;

    $commentClass = 'comment-body';
    if ($comments->levels > 0) {
        $commentClass .= ' comment-child';
        $commentClass .= ($comments->levels % 2 == 0) ? ' comment-level-even' : ' comment-level-odd';
    } else {
        $commentClass .= ' comment-parent';
    }

    // 超过最大缩进层级的评论平铺显示，并添加 @回复对象 提及
    $parentAuthor = '';
    if ($comments->levels > $maxVisualDepth) {
        $commentClass .= ' comment-flat';
        if ($comments->parent && isset($commentAuthors[$comments->parent])) {
            $parentAuthor = $commentAuthors[$comments->parent];
        }
    }

    $commentClass .= ($comments->sequence % 2 == 0) ? ' comment-even' : ' comment-odd';
    if ($comments->authorId && $comments->authorId == $comments->ownerId) {
        $commentClass .= ' comment-by-author';
    }

    $avatarUrl = shufei_get_gravatar_url($comments->mail, 40);
    $authorName = htmlspecialchars($comments->author);
    $parentAuthorName = $parentAuthor ? htmlspecialchars($parentAuthor) : '';
?>
    <li itemscope itemtype="http://schema.org/UserComments" id="<?php $comments->theId(); ?>" class="<?php echo $commentClass; ?>">
        <div class="comment-avatar">
            <img src="<?php echo $avatarUrl; ?>" alt="<?php echo $authorName; ?>" class="avatar" width="40" height="40" />
        </div>
        <div class="comment-main">
            <div class="comment-header">
                <span class="comment-author-name" itemprop="creator" itemscope itemtype="http://schema.org/Person">
                    <span itemprop="name"><?php $comments->author(); ?></span>
                </span>
                <?php if ($parentAuthorName): ?>
                <span class="comment-reply-to">
                    <i class="fa fa-arrow-right"></i>
                    <a href="#<?php echo 'comment-' . $comments->parent; ?>">@<?php echo $parentAuthorName; ?></a>
                </span>
                <?php endif; ?>
                <span class="comment-time">
                    <?php if ($comments->levels <= 0): ?>
                    <a href="<?php $comments->permalink(); ?>">
                    <?php endif; ?>
                        <time itemprop="commentTime" datetime="<?php $comments->date('c'); ?>">
                            <?php $comments->date($options->dateFormat); ?>
                        </time>
                    <?php if ($comments->levels <= 0): ?>
                    </a>
                    <?php endif; ?>
                </span>
                <?php if ('approved' !== $comments->status): ?>
                    <em class="comment-awaiting-moderation"><i class="fa fa-clock-o"></i> 待审核</em>
                <?php endif; ?>
                <span class="comment-reply-btn">
                    <?php $comments->reply('<i class="fa fa-reply"></i> 回复'); ?>
                </span>
            </div>
            <div class="comment-content" itemprop="commentText">
                <?php
                $commentOptions = \Typecho\Widget::widget('Widget_Options');
                if ($commentOptions->commentsMarkdown) {
                    $commentText = $comments->text;
                    if ($commentText !== null) {
                        $commentHtml = shufei_parse_comment_markdown($commentText);
                    } else {
                        ob_start();
                        $comments->content();
                        $commentHtml = ob_get_clean();
                        $commentHtml = shufei_parse_emoji_code($commentHtml, $commentOptions);
                    }
                } else {
                    ob_start();
                    $comments->content();
                    $commentHtml = ob_get_clean();
                    $commentHtml = shufei_parse_emoji_code($commentHtml, $commentOptions);
                }
                // 解析评论中的文章引用标记 [quote]...[/quote] 为 <blockquote>
                $commentHtml = shufei_parse_comment_quote($commentHtml);
                echo $commentHtml;
                ?>
            </div>
        </div>

        <?php if ($comments->children): ?>
            <ol class="comment-children" itemprop="discusses">
            <?php $comments->threadedComments(); ?>
            </ol>
        <?php endif; ?>
    </li>
<?php
}
?>

<div id="comments">
    <?php $this->comments()->to($comments); ?>
    <?php if ($comments->have()): ?>
        <div class="comments-header">
            <h3>
                <i class="fa fa-comments"></i>
                <?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?>
            </h3>
        </div>

        <ol class="comment-list">
        <?php $comments->listComments(['before' => '', 'after' => '']); ?>
        </ol>

        <nav class="page-navigator">
            <?php $comments->pageNav('<i class="fa fa-angle-left"></i> ' . _t('上一页'), _t('下一页') . ' <i class="fa fa-angle-right"></i>'); ?>
        </nav>

    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
        <div id="<?php $this->respondId(); ?>" class="respond">
            <div class="cancel-comment-reply">
                <?php $comments->cancelReply('<i class="fa fa-times"></i> ' . _t('取消回复')); ?>
            </div>

            <h3 id="response">
                <i class="fa fa-pencil-square-o"></i><?php _e('添加新评论'); ?>
            </h3>

            <form method="post" action="<?php $this->commentUrl() ?>" id="comment-form" class="no-pjax" role="form" data-token="<?php echo $this->security->getToken($this->request->getRequestUrl()); ?>">
                <?php if ($this->user->hasLogin()): ?>
                    <div class="comment-logged-info">
                        <i class="fa fa-user-circle"></i>
                        <?php _e('登录身份'); ?>: <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>.
                        <a href="<?php $this->options->logoutUrl(); ?>" title="Logout">
                            <i class="fa fa-sign-out"></i><?php _e('退出'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="comment-form-fields">
                        <div class="form-field">
                            <label for="author" class="required">
                                <i class="fa fa-user"></i><?php _e('称呼'); ?>
                            </label>
                            <input type="text" name="author" id="author" class="text"
                                   value="<?php $this->remember('author'); ?>" required placeholder="<?php _e('你的昵称'); ?>"/>
                        </div>
                        <div class="form-field">
                            <label for="mail"<?php if ($this->options->commentsRequireMail): ?> class="required"<?php endif; ?>>
                                <i class="fa fa-envelope"></i><?php _e('Email'); ?>
                            </label>
                            <input type="email" name="mail" id="mail" class="text"
                                   value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?> placeholder="<?php _e('你的邮箱'); ?>"/>
                        </div>
                        <div class="form-field">
                            <label for="url"<?php if ($this->options->commentsRequireUrl): ?> class="required"<?php endif; ?>>
                                <i class="fa fa-link"></i><?php _e('网站'); ?>
                            </label>
                            <input type="url" name="url" id="url" class="text" placeholder="<?php _e('http://'); ?>"
                                   value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireUrl): ?> required<?php endif; ?> />
                        </div>
                    </div>
                <?php endif; ?>
                <div class="form-field form-field-textarea">
                    <label for="textarea" class="required">
                        <i class="fa fa-commenting"></i><?php _e('内容'); ?>
                    </label>
                    <textarea rows="6" cols="50" name="text" id="textarea" class="textarea"
                              required placeholder="<?php _e('写下你的评论...'); ?>"><?php $this->remember('text'); ?></textarea>
                    <div class="emoji-panel" id="emoji-panel">
                        <button type="button" class="emoji-toggle" id="emoji-toggle" aria-label="<?php _e('表情面板'); ?>" aria-expanded="false">
                            <i class="fa fa-smile-o" aria-hidden="true"></i> <?php _e('表情'); ?>
                        </button>
                    </div>
                </div>

                <?php if (shufei_is_turnstile_enabled() && !empty(shufei_get_turnstile_site_key())): ?>
                <div class="turnstile-container">
                    <div id="cf-turnstile" class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(shufei_get_turnstile_site_key()); ?>" data-theme="auto"></div>
                </div>
                <?php elseif (shufei_is_captcha_enabled()): ?>
                <div class="captcha-container">
                    <div class="captcha-row">
                        <img id="captcha-img" class="captcha-img" src="<?php echo $this->options->themeUrl('core/captcha.php?type=' . shufei_get_captcha_char_type() . '&length=' . shufei_get_captcha_length()); ?>" alt="验证码" title="点击刷新验证码" onclick="this.src='<?php echo $this->options->themeUrl('core/captcha.php?type=' . shufei_get_captcha_char_type() . '&length=' . shufei_get_captcha_length()); ?>&t='+Date.now()" />
                        <input type="text" name="captcha_code" id="captcha-code" class="captcha-input" placeholder="请输入验证码" autocomplete="off" required />
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="submit" id="comment-submit-btn">
                        <i class="fa fa-paper-plane"></i><?php _e('提交评论'); ?>
                    </button>
                    <span class="comment-submit-tip" id="comment-submit-tip"></span>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="respond comment-closed">
            <h3><i class="fa fa-lock"></i><?php _e('评论已关闭'); ?></h3>
        </div>
    <?php endif; ?>
</div>
