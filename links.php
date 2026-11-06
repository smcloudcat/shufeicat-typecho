<?php
/**
 * 友链展示
 *
 * @package custom
 * @author ShuFeiCat
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
$this->need('sidebar-left.php');

$linksList = shufei_parse_links();
?>

<div class="col-mb-12 col-8" id="main" role="main">
    <article class="post links-page" itemscope itemtype="http://schema.org/BlogPosting">
        <header class="post-header links-header">
            <div class="links-header-inner">
                <div class="links-icon">
                    <i class="fa fa-link"></i>
                </div>
                <h1 class="post-title" itemprop="name headline"><?php $this->title() ?></h1>
                <p class="links-desc">
                    <i class="fa fa-users"></i> 共 <?php echo count($linksList); ?> 个友链
                </p>
            </div>
        </header>

        <div class="post-content" itemprop="articleBody">
            <?php echo shufei_render_post_content($this); ?>

            <?php if (empty($linksList)): ?>
            <div class="links-not-configured">
                <i class="fa fa-link"></i>
                <h3>暂无友链</h3>
                <p>请在主题设置中配置友链数据</p>
            </div>
            <?php else: ?>
            <div class="links-cards-list">
                <?php foreach ($linksList as $link): ?>
                <?php
                    // 提取首字母作为头像回退
                    $_firstLetter = mb_substr(trim($link['name']), 0, 1, 'UTF-8');
                    $_host = parse_url($link['url'], PHP_URL_HOST);
                    $_displayHost = $_host ? $_host : $link['url'];
                ?>
                <div class="links-card">
                    <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener noreferrer" class="links-card-link">
                        <div class="links-card-avatar">
                            <?php if (!empty($link['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($link['avatar']); ?>" alt="<?php echo htmlspecialchars($link['name']); ?>" onerror="this.style.display='none';this.parentNode.innerHTML='<span class=\'avatar-letter\'><?php echo htmlspecialchars($_firstLetter, ENT_QUOTES); ?></span>';">
                            <?php else: ?>
                            <span class="avatar-letter"><?php echo htmlspecialchars($_firstLetter); ?></span>
                            <?php endif; ?>
                            <span class="links-card-status" title="在线"></span>
                        </div>
                        <div class="links-card-body">
                            <h3 class="links-card-name"><?php echo htmlspecialchars($link['name']); ?></h3>
                            <?php if (!empty($link['description'])): ?>
                            <p class="links-card-desc"><?php echo htmlspecialchars($link['description']); ?></p>
                            <?php endif; ?>
                            <span class="links-card-url">
                                <i class="fa fa-external-link"></i>
                                <?php echo htmlspecialchars($_displayHost); ?>
                            </span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </article>
</div><!-- end #main-->

<?php $this->need('sidebar-right.php'); ?>
<?php $this->need('footer.php'); ?>
