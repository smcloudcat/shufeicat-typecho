<?php
/**
 * GitHub 项目展示
 *
 * @package custom
 * @author ShuFeiCat
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
$this->need('sidebar-left.php');

$options = $this->options;
$githubUsername = isset($options->githubUsername) ? trim($options->githubUsername) : '';
$repos = !empty($githubUsername) ? shufei_get_github_repos() : array();
?>

<div class="col-mb-12 col-8" id="main" role="main">
    <article class="post github-page" itemscope itemtype="http://schema.org/BlogPosting">
        <header class="post-header github-header">
            <div class="github-header-inner">
                <div class="github-icon">
                    <i class="fa fa-github"></i>
                </div>
                <h1 class="post-title" itemprop="name headline"><?php $this->title() ?></h1>
                <?php if (!empty($githubUsername)): ?>
                <p class="github-desc">
                    <i class="fa fa-user"></i> <a href="https://github.com/<?php echo htmlspecialchars($githubUsername); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($githubUsername); ?></a>
                    &nbsp;&nbsp;<i class="fa fa-code-fork"></i> <?php echo count($repos); ?> 个公开项目
                </p>
                <?php endif; ?>
            </div>
        </header>

        <div class="post-content" itemprop="articleBody">
            <?php echo shufei_render_post_content($this); ?>

            <?php if (empty($githubUsername)): ?>
            <div class="github-not-configured">
                <i class="fa fa-github"></i>
                <h3>未配置 GitHub 用户名</h3>
                <p>请在主题设置中填写 GitHub 用户名以展示项目列表</p>
            </div>
            <?php elseif (empty($repos)): ?>
            <div class="github-not-configured">
                <i class="fa fa-github"></i>
                <h3>暂无项目</h3>
                <p>无法获取 GitHub 项目信息，请检查用户名是否正确或稍后再试</p>
            </div>
            <?php else: ?>
            <div class="github-repos-list">
                <?php foreach ($repos as $repo): ?>
                <div class="github-repo-card">
                    <div class="repo-card-header">
                        <h3 class="repo-name">
                            <i class="fa fa-code-fork"></i>
                            <a href="<?php echo htmlspecialchars($repo['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($repo['name']); ?></a>
                        </h3>
                    </div>
                    <?php if (!empty($repo['description'])): ?>
                    <p class="repo-desc"><?php echo htmlspecialchars($repo['description']); ?></p>
                    <?php endif; ?>
                    <div class="repo-meta">
                        <?php if (!empty($repo['language'])): ?>
                        <span class="repo-meta-item">
                            <span class="repo-lang-dot" data-lang="<?php echo htmlspecialchars($repo['language']); ?>"></span>
                            <?php echo htmlspecialchars($repo['language']); ?>
                        </span>
                        <?php endif; ?>
                        <span class="repo-meta-item">
                            <i class="fa fa-star"></i> <?php echo $repo['stars']; ?>
                        </span>
                        <span class="repo-meta-item">
                            <i class="fa fa-code-fork"></i> <?php echo $repo['forks']; ?>
                        </span>
                        <span class="repo-meta-item">
                            <i class="fa fa-clock-o"></i> <?php echo date('Y-m-d', strtotime($repo['updated_at'])); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </article>
</div><!-- end #main-->

<?php $this->need('sidebar-right.php'); ?>
<?php $this->need('footer.php'); ?>
