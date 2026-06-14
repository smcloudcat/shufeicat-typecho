<?php
/**
 * 留言板
 *
 * @package custom
 * @author ShuFeiCat
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
$this->need('sidebar-left.php');
?>

<div class="col-mb-12 col-8" id="main" role="main">
    <article class="post guestbook-page" itemscope itemtype="http://schema.org/BlogPosting">
        <header class="post-header guestbook-header">
            <div class="guestbook-header-inner">
                <div class="guestbook-icon">
                    <i class="fa fa-envelope-o"></i>
                </div>
                <h1 class="post-title" itemprop="name headline"><?php $this->title() ?></h1>
                <p class="guestbook-desc">欢迎来到留言板，留下你的足迹吧~</p>
            </div>
        </header>

        <div class="post-content" itemprop="articleBody">
            <?php echo shufei_render_post_content($this); ?>
        </div>
    </article>

    <?php if ($this->allow('comment')): ?>
    <?php $this->need('comments.php'); ?>
    <?php endif; ?>
</div><!-- end #main-->

<?php $this->need('sidebar-right.php'); ?>
<?php $this->need('footer.php'); ?>
