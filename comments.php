<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<?php
// 递归渲染嵌套评论
function threadedComments($comments, $children, $depth = 0, $user) {
    foreach ($children as $comment):
        $commentClass = 'comment-body';
        if ($depth > 0) {
            $commentClass .= ' comment-child';
            $commentClass .= ($depth % 2 == 0) ? ' comment-level-even' : ' comment-level-odd';
        } else {
            $commentClass .= ' comment-parent';
        }
        $commentClass .= ($comment['sequence'] % 2 == 0) ? ' comment-even' : ' comment-odd';
        if (isset($comment['ownerId']) && $user->uid == $comment['ownerId']) {
            $commentClass .= ' comment-by-author';
        }
        
        $tmp = $comments->row;
        $comments->row = $comment;
        
        // 获取头像URL和纯文本作者名
        $avatarUrl = shufei_get_gravatar_url($comment['mail'], 32);
        $authorName = htmlspecialchars($comment['author']);
?>
        <li itemscope itemtype="http://schema.org/UserComments" id="<?php $comments->theId(); ?>" class="<?php echo $commentClass; ?>">
            <div class="comment-author" itemprop="creator" itemscope itemtype="http://schema.org/Person">
                <span itemprop="image">
                    <img src="<?php echo $avatarUrl; ?>" alt="<?php echo $authorName; ?>" class="avatar" width="32" height="32" />
                </span>
                <cite class="fn" itemprop="name"><?php $comments->author(); ?></cite>
            </div>
            <div class="comment-meta">
                <a href="<?php $comments->permalink(); ?>">
                    <time itemprop="commentTime" datetime="<?php $comments->date('c'); ?>">
                        <?php $comments->date($comments->options->commentDateFormat); ?>
                    </time>
                </a>
                <?php if ('approved' !== $comment['status']): ?>
                    <em class="comment-awaiting-moderation">您的评论正等待审核!</em>
                <?php endif; ?>
            </div>
            <div class="comment-content" itemprop="commentText">
                <?php $comments->content(); ?>
            </div>
            <div class="comment-reply">
                <?php $comments->reply('回复'); ?>
            </div>
            
            <?php if ($comment['children']): ?>
                <ol class="comment-children" itemprop="discusses">
                <?php threadedComments($comments, $comment['children'], $depth + 1, $user); ?>
                </ol>
            <?php endif; ?>
        </li>
<?php
        // 恢复行
        $comments->row = $tmp;
    endforeach;
}
?>

<div id="comments">
    <?php $this->comments()->to($comments); ?>
    <?php if ($comments->have()): ?>
        <h3>
            <i class="fa fa-comments"></i>
            <?php $this->commentsNum(_t('暂无评论'), _t('仅有一条评论'), _t('已有 %d 条评论')); ?>
        </h3>

        <ol class="comment-list">
        <?php
        // 手动遍历顶级评论
        while ($comments->next()):
            $commentClass = 'comment-body';
            if ($comments->levels > 0) {
                $commentClass .= ' comment-child';
                $commentClass .= ($comments->levels % 2 == 0) ? ' comment-level-even' : ' comment-level-odd';
            } else {
                $commentClass .= ' comment-parent';
            }
            $commentClass .= ($comments->sequence % 2 == 0) ? ' comment-even' : ' comment-odd';
            if ($this->user->uid == $comments->ownerId) {
                $commentClass .= ' comment-by-author';
            }
        ?>
            <?php
            // 获取头像URL和纯文本作者名
            $avatarUrl = shufei_get_gravatar_url($comments->mail, 32);
            $authorName = htmlspecialchars($comments->author);
            ?>
            <li itemscope itemtype="http://schema.org/UserComments" id="<?php $comments->theId(); ?>" class="<?php echo $commentClass; ?>">
                <div class="comment-author" itemprop="creator" itemscope itemtype="http://schema.org/Person">
                    <span itemprop="image">
                        <img src="<?php echo $avatarUrl; ?>" alt="<?php echo $authorName; ?>" class="avatar" width="32" height="32" />
                    </span>
                    <cite class="fn" itemprop="name"><?php $comments->author(); ?></cite>
                </div>
                <div class="comment-meta">
                    <a href="<?php $comments->permalink(); ?>">
                        <time itemprop="commentTime" datetime="<?php $comments->date('c'); ?>">
                            <?php $comments->date($this->options->commentDateFormat); ?>
                        </time>
                    </a>
                    <?php if ('approved' !== $comments->status): ?>
                        <em class="comment-awaiting-moderation">您的评论正等待审核!</em>
                    <?php endif; ?>
                </div>
                <div class="comment-content" itemprop="commentText">
                    <?php $comments->content(); ?>
                </div>
                <div class="comment-reply">
                    <?php $comments->reply('回复'); ?>
                </div>
                
                <?php if ($comments->children): ?>
                    <ol class="comment-children" itemprop="discusses">
                    <?php threadedComments($comments, $comments->children, 1, $this->user); ?>
                    </ol>
                <?php endif; ?>
            </li>
        <?php endwhile; ?>
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
            
            <form method="post" action="<?php $this->commentUrl() ?>" id="comment-form" role="form">
                <?php if ($this->user->hasLogin()): ?>
                    <p>
                        <i class="fa fa-user-circle"></i>
                        <?php _e('登录身份'); ?>: <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>. 
                        <a href="<?php $this->options->logoutUrl(); ?>" title="Logout">
                            <i class="fa fa-sign-out"></i><?php _e('退出'); ?>
                        </a>
                    </p>
                <?php else: ?>
                    <div class="row">
                        <div class="col-4">
                            <p>
                                <label for="author" class="required">
                                    <i class="fa fa-user"></i><?php _e('称呼'); ?>
                                </label>
                                <input type="text" name="author" id="author" class="text"
                                       value="<?php $this->remember('author'); ?>" required/>
                            </p>
                        </div>
                        <div class="col-4">
                            <p>
                                <label for="mail"<?php if ($this->options->commentsRequireMail): ?> class="required"<? endif; ?>>
                                    <i class="fa fa-envelope"></i><?php _e('Email'); ?>
                                </label>
                                <input type="email" name="mail" id="mail" class="text"
                                       value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?> />
                            </p>
                        </div>
                        <div class="col-4">
                            <p>
                                <label for="url"<?php if ($this->options->commentsRequireUrl): ?> class="required"<? endif; ?>>
                                    <i class="fa fa-link"></i><?php _e('网站'); ?>
                                </label>
                                <input type="url" name="url" id="url" class="text" placeholder="<?php _e('http://'); ?>"
                                       value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireUrl): ?> required<?php endif; ?> />
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                <p>
                    <label for="textarea" class="required">
                        <i class="fa fa-commenting"></i><?php _e('内容'); ?>
                    </label>
                    <textarea rows="8" cols="50" name="text" id="textarea" class="textarea"
                              required><?php $this->remember('text'); ?></textarea>
                </p>
                
                <?php if (shufei_is_turnstile_enabled() && !empty(shufei_get_turnstile_site_key())): ?>
                <div class="turnstile-container" style="margin: 15px 0;">
                    <div id="cf-turnstile" class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(shufei_get_turnstile_site_key()); ?>" data-theme="auto"></div>
                </div>
                <?php endif; ?>
                
                <p>
                    <button type="submit" class="submit">
                        <i class="fa fa-paper-plane"></i><?php _e('提交评论'); ?>
                    </button>
                </p>
            </form>
        </div>
    <?php else: ?>
        <div class="respond">
            <h3><i class="fa fa-lock"></i><?php _e('评论已关闭'); ?></h3>
        </div>
    <?php endif; ?>
</div>