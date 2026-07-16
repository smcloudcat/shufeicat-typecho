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
    <article class="post post-single <?php echo !empty($thumbnail) ? 'has-thumbnail' : ''; ?>" data-cid="<?php echo $this->cid; ?>" itemscope itemtype="http://schema.org/BlogPosting">
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
        
        <div class="post-content" itemprop="articleBody">
            <?php if ($this->hidden): ?>
                <div class="password-protection">
                    <div class="password-lock-icon">
                        <i class="fa fa-lock"></i>
                    </div>
                    <h2 class="password-title">文章已加密~</h2>
                    <p class="password-desc">这是一篇受密码保护的文章，请输入正确的密码来查看全文内容。</p>
                    <?php $this->content(); ?>
                    <p class="password-hint"><i class="fa fa-info-circle"></i> 请联系博主获取访问密码</p>
                </div>
            <?php else: ?>
                <?php echo shufei_render_post_content($this); ?>
            <?php endif; ?>
        </div>
        
        <?php if (count($this->tags) > 0): ?>
        <p itemprop="keywords" class="tags">
            <i class="fa fa-tags"></i>
            <?php _e('标签'); ?>: 
            <?php $this->tags(', ', true, 'none'); ?>
        </p>
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

    <?php $this->need('comments.php'); ?>
</div><!-- end #main-->

<?php $this->need('sidebar-right.php'); ?>

<?php $this->need('footer.php'); ?>
