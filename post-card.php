<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/**
 * 文章卡片渲染模板
 *
 * 变量说明（调用前需设置）：
 * @var object  $post          文章对象
 * @var string  $thumbnail     缩略图URL
 * @var string  $excerpt       文章摘要
 * @var bool    $isSticky      是否置顶
 * @var bool    $hasThumb      是否有缩略图
 * @var string  $postListStyle 列表样式 (classic / 其他)
 * @var array   $stickyCids    置顶文章CID数组
 */
?>

<?php if ($postListStyle === 'classic'): ?>
<article class="post <?php echo $hasThumb ? 'has-thumbnail' : ''; ?> <?php echo $isSticky ? 'post-sticky' : ''; ?>"
         data-cid="<?php echo $post->cid; ?>"
         itemscope itemtype="http://schema.org/BlogPosting"
         style="<?php echo $hasThumb ? 'background-image: url(' . shufei_sanitize_url($thumbnail) . ');' : ''; ?>">
    <a href="<?php echo $post->permalink(); ?>" class="post-link" aria-label="<?php $post->title(); ?>"></a>

    <?php if ($isSticky): ?>
    <div class="sticky-badge">
        <i class="fa fa-thumb-tack"></i>
        <span>置顶</span>
    </div>
    <?php endif; ?>

    <div class="post-overlay">
        <header class="post-header">
            <h2 class="post-title" itemprop="name headline">
                <a itemprop="url" href="<?php $post->permalink(); ?>"><?php $post->title(); ?></a>
            </h2>
        </header>
        <div class="post-content post-excerpt" itemprop="articleBody">
            <?php if ($post->hidden): ?>
                <p class="excerpt-text"><i class="fa fa-lock"></i> 此文章已加密，请输入密码查看</p>
            <?php else: ?>
                <p class="excerpt-text"><?php echo htmlspecialchars($excerpt); ?></p>
            <?php endif; ?>
            <div class="post-footer">
                <ul class="post-meta-inline">
                    <li itemprop="author" itemscope itemtype="http://schema.org/Person">
                        <a itemprop="name" href="<?php $post->author->permalink(); ?>" rel="author"><?php $post->author(); ?></a>
                    </li>
                    <li>
                        <time datetime="<?php $post->date('c'); ?>" itemprop="datePublished"><?php $post->date(); ?></time>
                    </li>
                    <li><?php $post->category(','); ?></li>
                    <li itemprop="interactionCount">
                        <a itemprop="discussionUrl" href="<?php $post->permalink() ?>#comments"><?php $post->commentsNum(_t('0'), _t('1'), _t('%d')); ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</article>

<?php else: ?>
<article class="post <?php echo $hasThumb ? 'has-thumbnail' : 'no-thumbnail'; ?> <?php echo $isSticky ? 'post-sticky' : ''; ?>"
         data-cid="<?php echo $post->cid; ?>"
         itemscope itemtype="http://schema.org/BlogPosting">
    <a href="<?php echo $post->permalink(); ?>" class="post-link" aria-label="<?php $post->title(); ?>"></a>

    <?php if ($isSticky): ?>
    <div class="sticky-badge">
        <i class="fa fa-thumb-tack"></i>
        <span>置顶</span>
    </div>
    <?php endif; ?>

    <?php if ($hasThumb): ?>
    <div class="post-thumbnail-side">
        <a href="<?php $post->permalink(); ?>" class="thumbnail-link-side" title="<?php $post->title(); ?>">
            <img src="<?php echo shufei_sanitize_url($thumbnail); ?>" alt="<?php $post->title(); ?>" class="thumbnail-img-side" />
        </a>
    </div>
    <?php endif; ?>

    <div class="post-body">
        <header class="post-header">
            <h2 class="post-title" itemprop="name headline">
                <a itemprop="url" href="<?php $post->permalink(); ?>"><?php $post->title(); ?></a>
            </h2>
            <ul class="post-meta-top">
                <li><i class="fa fa-user"></i> <a href="<?php $post->author->permalink(); ?>" rel="author"><?php $post->author(); ?></a></li>
                <li><i class="fa fa-calendar"></i> <time datetime="<?php $post->date('c'); ?>" itemprop="datePublished"><?php $post->date(); ?></time></li>
                <li><i class="fa fa-folder-o"></i> <?php $post->category(','); ?></li>
            </ul>
        </header>
        <div class="post-excerpt" itemprop="articleBody">
            <?php if ($post->hidden): ?>
                <p class="excerpt-text"><i class="fa fa-lock"></i> 此文章已加密，请输入密码查看</p>
            <?php else: ?>
                <p class="excerpt-text"><?php echo htmlspecialchars($excerpt); ?></p>
            <?php endif; ?>
        </div>
        <div class="post-footer">
            <ul class="post-meta-inline">
                <?php if (!$post->hidden): ?>
                <li itemprop="interactionCount">
                    <a itemprop="discussionUrl" href="<?php $post->permalink() ?>#comments" title="评论"><i class="fa fa-comment-o"></i> <?php $post->commentsNum(_t('0'), _t('1'), _t('%d')); ?></a>
                </li>
                <?php endif; ?>
            </ul>
            <a href="<?php $post->permalink(); ?>" class="read-more">
                <?php _e('阅读全文 <i class="fa fa-angle-double-right"></i>'); ?>
            </a>
        </div>
    </div>
</article>
<?php endif; ?>
