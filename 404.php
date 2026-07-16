<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<?php $this->need('sidebar-left.php'); ?>

<!-- 主内容区域 -->
<div class="col-mb-12 col-8" id="main" role="main">

    <div class="error-page">
        <h2 class="post-title">404 - <?php _e('页面没找到'); ?></h2>
        <p><?php _e('你想查看的页面已被转移或删除了, 要不搜索看看: '); ?></p>
        <form method="get" action="<?php $this->options->siteUrl(); ?>">
            <p><label for="s-404" class="sr-only"><?php _e('搜索关键词'); ?></label><input type="text" name="s" id="s-404" class="text" autofocus placeholder="<?php _e('输入关键词搜索'); ?>"/></p>
            <p>
                <button type="submit" class="submit"><?php _e('搜索'); ?></button>
            </p>
        </form>
    </div>

</div><!-- end #main-->

<?php $this->need('sidebar-right.php'); ?>

<?php $this->need('footer.php'); ?>
