<?php
/**
 * ShuFeiCat主题 - 主页模板
 *
 * @package ShuFeiCat
 * @author YunCat
 * @version 1.5.0-rc.5
 * @link https://lwcat.cn
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<?php $this->need('sidebar-left.php'); ?>

<!-- 主内容区域 -->
<div class="col-mb-12 col-8" id="main" role="main">
    <?php if (!($this->is('index')) && !($this->is('post'))): ?>
    <div class="archive-title">
        <i class="fa fa-folder-open-o"></i>
        <?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ''); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($this->have()): ?>
    <div id="ajax-post-list" class="post-list-<?php echo !empty($this->options->postListStyle) ? $this->options->postListStyle : 'classic'; ?>">
    <?php
    // 获取所有置顶文章的CID
    $stickyCids = shufei_get_sticky_cids();

    // 分离置顶文章和普通文章
    $stickyPosts = array();
    $normalPosts = array();

    while ($this->next()) {
        if (in_array($this->cid, $stickyCids)) {
            $stickyPosts[] = clone $this;
        } else {
            $normalPosts[] = clone $this;
        }
    }

    // 在首页第一页时，获取不在当前页的置顶文章
    $isFirstPage = $this->is('index') && intval($this->_currentPage) <= 1;

    if ($isFirstPage && !empty($stickyCids)) {
        // 获取当前页已有的置顶文章CID
        $currentStickyCids = array();
        foreach ($stickyPosts as $sp) {
            $currentStickyCids[] = $sp->cid;
        }

        // 获取不在当前页的置顶文章CID
        $missingCids = array_diff($stickyCids, $currentStickyCids);

        if (!empty($missingCids)) {
            $db = \Typecho\Db::get();
            $query = $db->select('table.contents.*')->from('table.contents')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', \Typecho\Date::time())
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.cid IN ?', array_values($missingCids))
                ->order('table.contents.created', \Typecho\Db::SORT_DESC);

            $stickyFromWidget = \Widget\Contents\From::allocWithAlias('sticky_extra', ['query' => $query]);
            while ($stickyFromWidget->next()) {
                $stickyPosts[] = clone $stickyFromWidget;
            }
        }

        // 合并文章列表：置顶文章在前
        $allPosts = array_merge($stickyPosts, $normalPosts);
    } elseif (!empty($stickyCids) && $this->is('index')) {
        // 非首页第一页，跳过置顶文章（已在第一页显示）
        $allPosts = $normalPosts;
    } else {
        // 非首页（分类、标签等），保持原有逻辑
        $allPosts = array_merge($stickyPosts, $normalPosts);
    }

    $postListStyle = !empty($this->options->postListStyle) ? $this->options->postListStyle : 'classic';
    ?>
    <?php foreach ($allPosts as $post): ?>
        <?php
        $thumbnail = shufei_get_post_thumbnail($post);
        
        $excerpt = '';
        if (!$post->hidden) {
            $customExcerpt = $post->fields->excerpt;
            if (!empty($customExcerpt)) {
                $excerpt = $customExcerpt;
            } else {
                // 使用 text（原始 Markdown 文本）而非 content（解析后的 HTML），
                // 因为 clone 后的对象访问 content 可能因钩子依赖导致返回空值
                $rawText = $post->text ?? '';
                // 去掉 Typecho 的 <!--markdown--> 前缀
                $rawText = preg_replace('/^<!--markdown-->/', '', $rawText);
                // 去掉 Markdown 图片语法、链接语法等，避免产生干扰文本
                $rawText = preg_replace('/!\[.*?\]\(.*?\)/', '', $rawText);
                $rawText = preg_replace('/\[([^\]]*)\]\(.*?\)/', '$1', $rawText);
                $rawText = preg_replace('/^#{1,6}\s+/m', '', $rawText);
                $rawText = preg_replace('/^[>\-\*\+]\s*/m', '', $rawText);
                // 去掉数学公式语法，避免摘要中输出公式占位符或原始公式代码
                $rawText = preg_replace('/\$\$[\s\S]+?\$\$/', '', $rawText);
                $rawText = preg_replace('/\\\\\[[\s\S]+?\\\\\]/', '', $rawText);
                $rawText = preg_replace('/(?<!\$)\$(?!\$)[^\$\n]+?(?<!\$)\$(?!\$)/', '', $rawText);
                $rawText = preg_replace('/\\\\\([\s\S]+?\\\\\)/', '', $rawText);
                $content = strip_tags($rawText);
                $content = preg_replace('/\s+/', ' ', trim($content));
                if (mb_strlen($content, 'UTF-8') > 80) {
                    $excerpt = mb_substr($content, 0, 80, 'UTF-8') . '...';
                } else {
                    $excerpt = $content;
                }
            }
        }
        
        $isSticky = in_array($post->cid, $stickyCids);
        $hasThumb = !empty($thumbnail);
        
        include 'post-card.php';
        ?>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
        <article class="post">
            <div class="post-content text-center" style="padding: 60px 20px;">
                <i class="fa fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                <h2 class="post-title"><?php _e('没有找到内容'); ?></h2>
                <p style="color: #999; margin-top: 15px;"><?php _e('抱歉,您访问的内容不存在或已被删除'); ?></p>
            </div>
        </article>
    <?php endif; ?>

    <?php if (!empty($this->options->postListPager) && $this->options->postListPager === 'loadmore'): ?>
        <?php
        $lmCurrent = intval($this->_currentPage);
        $lmTotal = intval($this->getTotalPage());
        $lmNext = '';
        if ($lmCurrent < $lmTotal) {
            ob_start();
            $this->pageLink('__NEXT__', 'next');
            $lmHtml = ob_get_clean();
            if (preg_match('/href="([^"]+)"/i', $lmHtml, $lmM)) {
                $lmNext = htmlspecialchars($lmM[1]);
            }
        }
        ?>
        <div class="load-more-wrap" id="load-more-wrap"<?php echo $lmNext ? ' data-next="' . $lmNext . '"' : ''; ?>>
            <?php if ($lmNext): ?>
            <button type="button" class="load-more-btn" id="load-more-btn">
                <i class="fa fa-chevron-down"></i> <?php _e('加载更多'); ?>
            </button>
            <div class="load-more-tip"><?php _e('已加载'); ?> <?php echo $lmCurrent; ?> <?php _e('/'); ?> <?php echo $lmTotal; ?> <?php _e('页'); ?></div>
            <?php else: ?>
            <div class="load-more-end"><?php _e('没有更多内容了'); ?></div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <nav class="page-navigator" id="ajax-page-nav">
            <?php $this->pageNav('<i class="fa fa-angle-left"></i> ' . _t('上一页'), _t('下一页') . ' <i class="fa fa-angle-right"></i>', 2); ?>
        </nav>
    <?php endif; ?>
</div><!-- end #main-->

<?php $this->need('sidebar-right.php'); ?>

<?php
// 首页弹窗公告
$noticeMode = !empty($this->options->noticePopupMode) ? $this->options->noticePopupMode : 'off';
$noticeContent = isset($this->options->noticePopupContent) ? trim($this->options->noticePopupContent) : '';
if ($this->is('index') && $noticeMode !== 'off' && $noticeContent !== ''):
    $noticeTitle = !empty($this->options->noticePopupTitle) ? $this->options->noticePopupTitle : '公告';
    $noticeHash = substr(md5($noticeTitle . '|' . $noticeContent), 0, 12);
?>
<div id="shufei-notice-mask" data-mode="<?php echo htmlspecialchars($noticeMode); ?>" data-hash="<?php echo htmlspecialchars($noticeHash); ?>" hidden>
    <div class="shufei-notice-dialog" role="dialog" aria-modal="true" aria-labelledby="shufei-notice-title">
        <div class="shufei-notice-head">
            <span id="shufei-notice-title"><i class="fa fa-bullhorn"></i> <?php echo htmlspecialchars($noticeTitle); ?></span>
            <button type="button" class="shufei-notice-close" id="shufei-notice-close" aria-label="<?php _e('关闭'); ?>">&times;</button>
        </div>
        <div class="shufei-notice-body"><?php echo $noticeContent; ?></div>
        <div class="shufei-notice-foot">
            <button type="button" class="shufei-notice-ok" id="shufei-notice-ok"><?php _e('我知道了'); ?></button>
        </div>
    </div>
</div>
<style>
#shufei-notice-mask{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:20px;}
#shufei-notice-mask[hidden]{display:none;}
.shufei-notice-dialog{background:var(--card-bg,#fff);color:inherit;max-width:480px;width:100%;max-height:80vh;display:flex;flex-direction:column;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.25);overflow:hidden;animation:shufei-notice-in .25s ease;}
@keyframes shufei-notice-in{from{opacity:0;transform:translateY(12px) scale(.97);}to{opacity:1;transform:none;}}
.shufei-notice-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;font-size:16px;font-weight:600;border-bottom:1px solid rgba(128,128,128,.2);}
.shufei-notice-head i{color:var(--primary-color,#FF6B6B);}
.shufei-notice-close{background:none;border:none;font-size:22px;line-height:1;cursor:pointer;color:inherit;opacity:.6;padding:0 4px;}
.shufei-notice-close:hover{opacity:1;color:var(--primary-color,#FF6B6B);}
.shufei-notice-body{padding:18px;overflow-y:auto;font-size:14px;line-height:1.7;word-break:break-word;}
.shufei-notice-body a{color:var(--primary-color,#FF6B6B);}
.shufei-notice-foot{padding:12px 18px 16px;text-align:right;border-top:1px solid rgba(128,128,128,.2);}
.shufei-notice-ok{background:linear-gradient(135deg,var(--primary-color,#FF6B6B) 0%,var(--primary-hover,#FF6B6B) 100%);color:#fff;border:none;border-radius:8px;padding:8px 22px;font-size:14px;cursor:pointer;box-shadow:0 4px 12px color-mix(in srgb,var(--primary-color,#FF6B6B) 30%,transparent);transition:transform .15s ease,box-shadow .15s ease;}
.shufei-notice-ok:hover{transform:translateY(-1px);box-shadow:0 6px 16px color-mix(in srgb,var(--primary-color,#FF6B6B) 40%,transparent);}
.shufei-notice-ok:active{transform:translateY(0);}
@media (max-width:480px){.shufei-notice-dialog{max-width:100%;}}
</style>
<script>
(function(){
    var mask = document.getElementById('shufei-notice-mask');
    if (!mask) return;
    var mode = mask.getAttribute('data-mode');
    var hash = mask.getAttribute('data-hash');
    var key = 'shufei_notice_' + hash;
    try {
        if (mode === 'once') {
            if (localStorage.getItem(key) === '1') return;
        } else if (mode === 'daily') {
            var today = new Date();
            var day = today.getFullYear() + '-' + (today.getMonth()+1) + '-' + today.getDate();
            if (localStorage.getItem(key) === day) return;
        }
    } catch (e) {}
    mask.hidden = false;
    document.body.style.overflow = 'hidden';
    function close(){
        try {
            if (mode === 'once') {
                localStorage.setItem(key, '1');
            } else if (mode === 'daily') {
                var today = new Date();
                localStorage.setItem(key, today.getFullYear() + '-' + (today.getMonth()+1) + '-' + today.getDate());
            }
        } catch (e) {}
        mask.hidden = true;
        document.body.style.overflow = '';
    }
    document.getElementById('shufei-notice-close').addEventListener('click', close);
    document.getElementById('shufei-notice-ok').addEventListener('click', close);
    mask.addEventListener('click', function(e){ if (e.target === mask) close(); });
})();
</script>
<?php endif; ?>

<?php $this->need('footer.php'); ?>
