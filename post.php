<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<?php $this->need('sidebar-left.php'); ?>

<div class="col-mb-12 col-8" id="main" role="main">
    <?php
    // 获取文章缩略图
    $thumbnail = shufei_get_post_thumbnail($this);
    // 获取文章提示弹窗内容
    $articleAlert = $this->fields->articleAlert;
    // 获取点赞功能控制
    $disableLike = $this->fields->disableLike;
    // 判断文章 AI 总结
    // 全局AI助手模式：开启后所有公开文章自动启用（密码文章未解锁时 $this->hidden 为 true，不显示）
    $aiSummaryEnabled = false;
    $aiGlobalMode = false;
    if (class_exists('AiSummary') && AiSummary::isEnabled() && !$this->hidden) {
        $aiGlobalMode = AiSummary::isGlobalMode();
        $aiSummaryEnabled = $aiGlobalMode ? true : AiSummary::isArticleEnabled($this->cid);
    }
    $aiSummaryCached = ($aiSummaryEnabled && class_exists('AiSummary')) ? AiSummary::getCached($this->cid) : null;
    // AI 摘要连续对话（off / summary_only / on）
    $aiChatMode = ($aiSummaryEnabled && class_exists('AiChat')) ? AiChat::getMode() : 'off';
    $aiChatOn = ($aiChatMode === 'on');
    ?>
    <?php if (!empty($articleAlert)): ?>
    <div class="article-alert-box" id="article-alert-box">
        <div class="article-alert-inner">
            <button class="article-alert-close" id="article-alert-close" title="关闭提示">
                <i class="fa fa-times"></i>
            </button>
            <div class="article-alert-icon">
                <i class="fa fa-bell-o"></i>
            </div>
            <div class="article-alert-content">
                <?php echo $articleAlert; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <article class="post post-single <?php echo !empty($thumbnail) ? 'has-thumbnail' : ''; ?>" data-cid="<?php echo $this->cid; ?>" data-ai-summary="<?php echo $aiSummaryEnabled ? '1' : '0'; ?>" itemscope itemtype="http://schema.org/BlogPosting">
        <header class="post-header" <?php if (!empty($thumbnail)): ?>style="background-image: url(<?php echo htmlspecialchars($thumbnail); ?>);"<?php endif; ?>>
            <div class="post-header-overlay">
                <h1 class="post-title" itemprop="name headline">
                    <a itemprop="url" href="<?php $this->permalink() ?>"><?php $this->title() ?></a>
                </h1>
                <ul class="post-meta">
                    <li itemprop="author" itemscope itemtype="http://schema.org/Person">
                        <i class="fa fa-user"></i>
                        <?php _e('作者'); ?>: <a itemprop="name" href="<?php $this->author->permalink(); ?>" rel="author"><?php $this->author(); ?></a>
                    </li>
                    <li>
                        <i class="fa fa-clock-o"></i>
                        <time datetime="<?php $this->date('c'); ?>" itemprop="datePublished"><?php $this->date(); ?></time>
                    </li>
                    <li>
                        <i class="fa fa-folder-o"></i>
                        <?php _e('分类'); ?>: <?php $this->category(','); ?>
                    </li>
                    <li itemprop="interactionCount">
                        <i class="fa fa-comments-o"></i>
                        <a itemprop="discussionUrl" href="<?php $this->permalink() ?>#comments"><?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></a>
                    </li>
                    <?php if (!empty($this->options->statsEnabled) && $this->options->statsEnabled === 'on'): ?>
                    <li>
                        <i class="fa fa-eye"></i>
                        <?php _e('阅读'); ?>: <span class="post-views-count" data-cid="<?php echo $this->cid; ?>"><?php echo shufei_get_views($this->cid); ?></span>
                    </li>
                    <li>
                        <i class="fa fa-thumbs-o-up"></i>
                        <?php _e('点赞'); ?>: <span class="post-likes-count" data-cid="<?php echo $this->cid; ?>"><?php echo shufei_get_likes($this->cid); ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </header>

        <?php if ($aiSummaryEnabled && ($aiSummaryCached !== null || $aiGlobalMode)): ?>
        <div class="ai-summary-box" id="ai-summary-box" data-cid="<?php echo $this->cid; ?>" data-ai-chat="<?php echo $aiChatOn ? '1' : '0'; ?>" data-ai-scope="<?php echo $aiGlobalMode ? 'global' : 'post'; ?>"<?php if ($aiChatOn): ?> data-ai-chat-max="<?php echo (class_exists('AiChat') ? AiChat::getMaxRounds() : 10); ?>"<?php endif; ?><?php if (!$aiSummaryCached && $aiGlobalMode): ?> data-ai-auto="1"<?php endif; ?>>
            <div class="ai-summary-header">
                <i class="fa fa-magic"></i> <span><?php _e('AI 文章摘要'); ?></span>
                <?php if ($aiSummaryCached !== null): ?>
                <span class="ai-summary-source"><?php _e('已缓存'); ?></span>
                <?php endif; ?>
            </div>
            <div class="ai-summary-content" id="ai-summary-content">
                <?php if ($aiSummaryCached !== null): ?>
                <p><?php echo nl2br(htmlspecialchars($aiSummaryCached)); ?></p>
                <?php else: ?>
                <div class="ai-summary-loading"><i class="fa fa-spinner fa-spin"></i> <?php _e('正在生成 AI 摘要...'); ?></div>
                <?php endif; ?>
            </div>
            <?php if ($aiChatOn): ?>
            <div class="ai-chat-area" id="ai-chat-area" hidden>
                <div class="ai-chat-messages" id="ai-chat-messages"></div>
                <div class="ai-chat-inputbar">
                    <input type="text" class="ai-chat-input" id="ai-chat-input" maxlength="1000"
                        placeholder="<?php _e('向 AI 提问本文或站内内容…'); ?>" autocomplete="off">
                    <button type="button" class="ai-chat-send" id="ai-chat-send" title="<?php _e('发送'); ?>">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>
                <div class="ai-chat-footnote"><?php _e('内容由 AI 生成，仅供参考'); ?></div>
            </div>
            <?php endif; ?>
            <div class="ai-summary-footer">
                <?php if ($aiChatOn): ?>
                <button type="button" class="ai-chat-toggle" id="ai-chat-toggle">
                    <i class="fa fa-comments-o"></i> <?php _e('询问 AI'); ?>
                </button>
                <button type="button" class="ai-chat-reset" id="ai-chat-reset" title="<?php _e('清空对话记录，开启新对话'); ?>">
                    <i class="fa fa-eraser"></i> <?php _e('新对话'); ?>
                </button>
                <?php endif; ?>
                <button type="button" class="ai-summary-regenerate" id="ai-summary-regenerate" title="<?php _e('重新生成'); ?>">
                    <i class="fa fa-refresh"></i> <?php _e('重新生成'); ?>
                </button>
                <button type="button" class="ai-summary-collapse" id="ai-summary-collapse" title="<?php _e('折叠'); ?>">
                    <i class="fa fa-chevron-up"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="post-content" itemprop="articleBody">
            <?php if ($this->hidden): ?>
                <?php echo shufei_render_password_protection($this); ?>
            <?php else: ?>
                <?php echo shufei_render_post_content($this); ?>
            <?php endif; ?>
        </div>
        
        <?php if (count($this->tags) > 0): ?>
        <div class="post-tags" itemprop="keywords">
            <span class="post-tags-label"><i class="fa fa-tags"></i><?php _e('标签'); ?></span>
            <?php foreach ($this->tags as $_tag): ?>
            <a class="tag-pill" href="<?php echo $_tag['permalink']; ?>"><?php echo htmlspecialchars($_tag['name']); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </article>

    <?php if (!empty($this->options->statsEnabled) && $this->options->statsEnabled === 'on' && $disableLike != '1'): ?>
    <div class="post-like-box">
        <div class="like-box-inner">
            <div class="like-box-stats">
                <span class="like-stat-item">
                    <i class="fa fa-eye"></i>
                    <span class="stat-label">阅读</span>
                    <span class="stat-value post-views-count" data-cid="<?php echo $this->cid; ?>"><?php echo shufei_get_views($this->cid); ?></span>
                </span>
                <span class="like-stat-divider">|</span>
                <span class="like-stat-item">
                    <i class="fa fa-thumbs-up"></i>
                    <span class="stat-label">点赞</span>
                    <span class="stat-value post-likes-count" data-cid="<?php echo $this->cid; ?>"><?php echo shufei_get_likes($this->cid); ?></span>
                </span>
            </div>
            <div class="post-action-btns">
                <button class="post-like-btn <?php echo shufei_has_liked($this->cid) ? 'liked' : ''; ?>" data-cid="<?php echo $this->cid; ?>" <?php echo shufei_has_liked($this->cid) ? 'disabled' : ''; ?>>
                    <i class="fa fa-thumbs-up"></i>
                    <span class="like-text"><?php echo shufei_has_liked($this->cid) ? '已点赞' : '点赞'; ?></span>
                </button>
                <button class="post-fav-btn" id="post-fav-btn" data-cid="<?php echo $this->cid; ?>" title="收藏文章">
                    <i class="fa fa-heart-o"></i>
                    <span class="fav-text">收藏</span>
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="post-like-box">
        <div class="like-box-inner">
            <div class="post-action-btns">
                <button class="post-fav-btn" id="post-fav-btn" data-cid="<?php echo $this->cid; ?>" title="收藏文章">
                    <i class="fa fa-heart-o"></i>
                    <span class="fav-text">收藏</span>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <ul class="post-near">
        <li>
            <i class="fa fa-angle-left"></i>
            <?php $this->thePrev('%s', _t('没有了')); ?>
        </li>
        <li>
            <?php $this->theNext('%s', _t('没有了')); ?>
            <i class="fa fa-angle-right"></i>
        </li>
    </ul>

    <?php
    // 文章投票卡片：仅在配置了投票问题时显示
    $_voteConfig = shufei_get_vote_config($this->cid);
    if ($_voteConfig):
        $_voteCheck = shufei_check_voted($this->cid);
        $_voteCounts = shufei_get_vote_counts($this->cid, $_voteConfig['options']);
        $_voteExpired = ($_voteConfig['deadline'] > 0 && time() > $_voteConfig['deadline']);
        $_voteShowResults = $_voteCheck['voted'] || $_voteExpired;
    ?>
    <div class="post-vote-card" id="post-vote-card"
         data-cid="<?php echo $this->cid; ?>"
         data-voted="<?php echo $_voteCheck['voted'] ? '1' : '0'; ?>"
         data-option="<?php echo $_voteCheck['option'] !== null ? $_voteCheck['option'] : ''; ?>"
         data-expired="<?php echo $_voteExpired ? '1' : '0'; ?>"
         data-deadline="<?php echo $_voteConfig['deadline']; ?>">
        <div class="vote-card-header">
            <div class="vote-card-title">
                <i class="fa fa-bar-chart-o"></i>
                <span><?php echo htmlspecialchars($_voteConfig['question']); ?></span>
            </div>
            <?php if ($_voteExpired): ?>
            <span class="vote-badge vote-badge-ended">已截止</span>
            <?php elseif ($_voteConfig['deadline'] > 0): ?>
            <span class="vote-badge vote-badge-active">截止 <?php echo htmlspecialchars($_voteConfig['deadlineText']); ?></span>
            <?php endif; ?>
        </div>
        <div class="vote-card-body">
            <?php foreach ($_voteConfig['options'] as $_idx => $_opt):
                $_cnt = isset($_voteCounts['counts'][$_idx]) ? $_voteCounts['counts'][$_idx] : 0;
                $_pct = $_voteCounts['total'] > 0 ? round(($_cnt / $_voteCounts['total']) * 100) : 0;
                $_isMine = ($_voteCheck['option'] === $_idx);
            ?>
            <div class="vote-option<?php echo $_isMine ? ' vote-option-mine' : ''; ?><?php echo $_voteShowResults ? ' vote-option-readonly' : ''; ?>"
                 data-index="<?php echo $_idx; ?>">
                <div class="vote-option-bar" style="width: <?php echo $_voteShowResults ? $_pct : 0; ?>%"></div>
                <div class="vote-option-content">
                    <span class="vote-option-label">
                        <?php if ($_isMine): ?><i class="fa fa-check-circle"></i><?php endif; ?>
                        <?php echo htmlspecialchars($_opt); ?>
                    </span>
                    <?php if ($_voteShowResults): ?>
                    <span class="vote-option-stats">
                        <span class="vote-pct"><?php echo $_pct; ?>%</span>
                        <span class="vote-cnt">(<?php echo $_cnt; ?>票)</span>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="vote-card-footer">
            <span class="vote-total">
                <i class="fa fa-users"></i>
                共 <strong><?php echo $_voteCounts['total']; ?></strong> 人参与
            </span>
            <?php if (!$_voteCheck['voted'] && !$_voteExpired): ?>
            <span class="vote-hint">点击选项进行投票</span>
            <?php elseif ($_voteCheck['voted']): ?>
            <span class="vote-hint">您已投票，感谢参与</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php $this->need('comments.php'); ?>
</div><!-- end #main-->

<?php $this->need('sidebar-right.php'); ?>

<?php $this->need('footer.php'); ?>
