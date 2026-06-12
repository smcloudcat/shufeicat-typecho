<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<?php $this->need('sidebar-left.php'); ?>

<div class="col-mb-12 col-8" id="main" role="main">
    <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
        <header class="post-header">
            <h1 class="post-title" itemprop="name headline">
                <?php $this->title() ?>
            </h1>
            <ul class="post-meta">
                <li>
                    <i class="fa fa-clock-o"></i>
                    <time datetime="<?php $this->date('c'); ?>" itemprop="datePublished"><?php $this->date(); ?></time>
                </li>
                <?php if ($this->user->hasLogin()): ?>
                <li>
                    <i class="fa fa-edit"></i>
                    <a href="<?php $this->options->adminUrl('write-page.php?cid=' . $this->cid); ?>">编辑页面</a>
                </li>
                <?php endif; ?>
            </ul>
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
