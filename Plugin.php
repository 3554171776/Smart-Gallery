<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Smart Gallery 相册
 * 
 * @package Smart Gallery
 * @author 落花雨记
 * @version 1.1.0
 * @link https://www.luohuayu.cn
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'Action.php';

class SmartGallery_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        $scripts = array(
            "CREATE TABLE IF NOT EXISTS `{$prefix}smart_gallery_albums` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL,
              `slug` varchar(100) DEFAULT '',
              `description` varchar(255) DEFAULT '',
              `cover` varchar(255) DEFAULT '',
              `password` varchar(100) DEFAULT '',
              `layout` varchar(20) DEFAULT 'grid',
              `sort_order` int(10) unsigned DEFAULT 0,
              `created` int(10) unsigned DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;",
            "CREATE TABLE IF NOT EXISTS `{$prefix}smart_gallery_images` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `album_id` int(10) unsigned NOT NULL,
              `filename` varchar(255) NOT NULL,
              `description` varchar(255) DEFAULT '',
              `type` varchar(20) DEFAULT 'image',
              `sort_order` int(10) unsigned DEFAULT 0,
              `created` int(10) unsigned DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `album_id` (`album_id`)
            ) DEFAULT CHARSET=utf8mb4;"
        );
        
        try { $db->query("ALTER TABLE `{$prefix}smart_gallery_albums` ADD COLUMN `layout` varchar(20) DEFAULT 'grid'"); } catch (Exception $e) { }
        try { $db->query("ALTER TABLE `{$prefix}smart_gallery_albums` ADD COLUMN `sort_order` int(10) unsigned DEFAULT 0"); } catch (Exception $e) { }
        try { $db->query("ALTER TABLE `{$prefix}smart_gallery_images` ADD COLUMN `sort_order` int(10) unsigned DEFAULT 0"); } catch (Exception $e) { }
        try { $db->query("ALTER TABLE `{$prefix}smart_gallery_images` ADD COLUMN `type` varchar(20) DEFAULT 'image'"); } catch (Exception $e) { }
        
        foreach ($scripts as $script) { try { $db->query($script); } catch (Exception $e) { } }

        Helper::addAction('smart-gallery', 'SmartGallery_Action');
        Helper::addPanel(3, 'SmartGallery/Panel.php', '相册管理', '管理图片相册', 'administrator');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('SmartGallery_Plugin', 'parseShortcode');
        
        return _t('插件启用成功，请配置插件设置。');
    }

    public static function deactivate()
    {
        Helper::removeAction('smart-gallery');
        Helper::removePanel(3, 'SmartGallery/Panel.php');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $currentConfig = self::getConfig($db, $prefix);

        $gdStatus = function_exists('gd_info') ? '<span style="color:#28a745;">✔ 已安装</span>' : '<span style="color:#dc3545;">✘ 未安装</span>';
        $webpFuncSupport = (function_exists('imagewebp')) ? '<span style="color:#28a745;">✔ 支持</span>' : '<span style="color:#dc3545;">✘ 不支持</span>';

        $currentImgQuality = isset($currentConfig['imgQuality']) ? $currentConfig['imgQuality'] : '80';
        $currentWebp = isset($currentConfig['webp']) ? ($currentConfig['webp'] == '1' ? '开启' : '关闭') : '关闭';
        $currentEffect = isset($currentConfig['openEffect']) ? $currentConfig['openEffect'] : 'slide';

        $pcCols = new Typecho_Widget_Helper_Form_Element_Text('pcCols', NULL, '4', _t('PC端每行显示数量'));
        $form->addInput($pcCols);
        
        $mobileCols = new Typecho_Widget_Helper_Form_Element_Text('mobileCols', NULL, '2', _t('移动端每行显示数量'));
        $form->addInput($mobileCols);

        $webp = new Typecho_Widget_Helper_Form_Element_Radio('webp', 
            array('1' => _t('开启'), '0' => _t('关闭')), 
            '0', 
            _t('WebP 压缩'), 
            _t('开启后将自动把图片转换为 WebP 格式。<br><span style="color:#666;">当前状态：<strong style="color:#467B96;">' . $currentWebp . '</strong></span>'));
        $form->addInput($webp);

        $imgQuality = new Typecho_Widget_Helper_Form_Element_Text('imgQuality', NULL, '80', _t('图片压缩质量 (0-100)'), 
            _t('建议 80，数值越小文件越小但质量越低。<br><span style="color:#666;">当前设置：<strong style="color:#467B96;">' . $currentImgQuality . '</strong></span>'));
        $form->addInput($imgQuality);

        $openEffect = new Typecho_Widget_Helper_Form_Element_Select('openEffect', 
            array(
                'slide' => '底部滑入 (经典)',
                'fade' => '淡入淡出 (平滑)',
                'rotate' => '转盘旋转 (创意)',
                'flip' => '卡片翻转 (3D)',
                'stack' => '层叠推开 (多任务)',
                'zoom' => '中心缩放 (聚焦)'
            ), 
            $currentEffect, 
            _t('相册打开动画'), 
            _t('选择前台相册弹窗的打开/关闭动画风格。<br><span style="color:#666;">当前：<strong style="color:#467B96;">' . self::getEffectName($currentEffect) . '</strong></span>'));
        $form->addInput($openEffect);

        $envHtml = '<div class="typecho-table-wrap" style="margin-top: 20px; background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #e9ecef;">
            <h4 style="margin-top:0; margin-bottom:10px; font-size:15px; color: #495057;">系统环境检测</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 13px; color: #495057;">
                <div><strong>GD 图形库：</strong>' . $gdStatus . '</div>
                <div><strong>WebP 函数：</strong>' . $webpFuncSupport . '</div>
            </div>
        </div>';

        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html(_t($envHtml));
        $form->addItem($layout); 
    }
    
    private static function getEffectName($effect) {
        $effects = array(
            'slide' => '底部滑入',
            'fade' => '淡入淡出',
            'rotate' => '转盘旋转',
            'flip' => '卡片翻转',
            'stack' => '层叠推开',
            'zoom' => '中心缩放'
        );
        return isset($effects[$effect]) ? $effects[$effect] : '底部滑入';
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    private static function safeUnserialize($value) {
        if (empty($value) || !is_string($value)) return null;
        $data = @unserialize($value);
        if (is_array($data)) return $data;
        $data = @json_decode($value, true);
        if (is_array($data)) return $data;
        return null;
    }

    private static function getConfig($db, $prefix) {
        $defaultConfig = ['pcCols' => '4', 'mobileCols' => '2', 'webp' => '0', 'imgQuality' => '80', 'openEffect' => 'slide'];
        try {
            $row = $db->fetchRow($db->select('value')->from($prefix . 'options')->where('name = ?', 'plugin:SmartGallery'));
            if ($row && isset($row['value']) && !empty($row['value'])) {
                $data = self::safeUnserialize($row['value']);
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        if ($value !== null && $value !== '') $defaultConfig[$key] = $value;
                    }
                }
            }
        } catch (Exception $e) {}
        return $defaultConfig;
    }

    public static function parseShortcode($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        if ($widget instanceof Widget_Archive) {
            $pattern = '/\[gallery\s*(?:id=(\d+))?\]/i';
            $content = preg_replace_callback($pattern, function($matches) {
                $albumId = isset($matches[1]) && $matches[1] ? intval($matches[1]) : 0;
                return self::output($albumId, true);
            }, $content);
        }
        return $content;
    }

    public static function output($targetAlbumId = 0, $return = false)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = self::getConfig($db, $prefix);
        
        $pcCols = isset($pluginOptions['pcCols']) ? intval($pluginOptions['pcCols']) : 4;
        $mobileCols = isset($pluginOptions['mobileCols']) ? intval($pluginOptions['mobileCols']) : 2;
        $openEffect = isset($pluginOptions['openEffect']) ? $pluginOptions['openEffect'] : 'slide';

        // CSS部分 - 重写了关键帧动画
        $css = '<style>
            .sg-album-grid { display: grid; grid-gap: 25px; margin: 20px 0; }
            @media (min-width: 768px) { .sg-album-grid { grid-template-columns: repeat(' . $pcCols . ', 1fr); } }
            @media (max-width: 767px) { .sg-album-grid { grid-template-columns: repeat(' . $mobileCols . ', 1fr); } }
            
            .sg-album-card { overflow: hidden; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); position: relative; background-color: #fff; transition: transform 0.3s, box-shadow 0.3s; display: flex; flex-direction: column; cursor: pointer; }
            .sg-album-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.12); }
            
            .sg-album-cover-box { position: relative; aspect-ratio: 4/3; overflow: hidden; background: #f5f5f5; }
            .sg-album-cover-box img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.5s; pointer-events: none; }
            
            .sg-lock-icon { position: absolute; top: 10px; right: 10px; z-index: 2; width: 36px; height: 36px; background: url(https://www.luohuayu.cn/usr/uploads/2026/03/mimi.png) no-repeat center center; background-size: contain; pointer-events: none; }
            
            .sg-album-card:hover .sg-album-cover-box img { transform: scale(1.05); }
            
            .sg-album-cover-title { position: absolute; bottom: 0; left: 0; right: 0; z-index: 2; background: linear-gradient(transparent, rgba(0,0,0,0.8)); color: #fff; padding: 30px 15px 10px; font-size: 16px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; pointer-events: none; }
            
            .sg-album-desc-box { padding: 12px 15px 15px; background: #fff; }
            .sg-album-desc { font-size: 13px; color: #666; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

            /* 弹窗容器 */
            .sg-modal { 
                display: none; 
                position: fixed; 
                top: 0; 
                left: 0; 
                width: 100%; 
                height: 100%; 
                z-index: 10000; 
                overflow: hidden;
                background: rgba(0,0,0,0);
                transition: background 0.3s ease;
                perspective: 1200px; /* 给翻转效果提供透视 */
            }
            .sg-modal.sg-active { 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                background: rgba(0,0,0,0.85);
            }
            .sg-modal.sg-closing { background: rgba(0,0,0,0); }
            
            /* 弹窗内容 - 初始状态 */
            .sg-modal-content { 
                width: 88%; max-width: 880px; max-height: 82vh; 
                background: #fff; border-radius: 10px; 
                display: flex; flex-direction: column;
                box-shadow: 0 25px 80px rgba(0,0,0,0.5);
                will-change: transform, opacity;
                transform-style: preserve-3d;
            }
            
            /* ========== 6种丝滑动画定义 ========== */
            
            /* 1. 底部滑入 */
            .sg-modal.sg-effect-slide.sg-active .sg-modal-content { animation: sg-slide-up 0.35s cubic-bezier(0.32, 0.72, 0, 1) forwards; }
            .sg-modal.sg-effect-slide.sg-closing .sg-modal-content { animation: sg-slide-down 0.3s cubic-bezier(0.32, 0.72, 0, 1) forwards; }
            @keyframes sg-slide-up { from { transform: translateY(100%); opacity: 1; } to { transform: translateY(0); opacity: 1; } }
            @keyframes sg-slide-down { from { transform: translateY(0); opacity: 1; } to { transform: translateY(100%); opacity: 1; } }
            
            /* 2. 淡入淡出 */
            .sg-modal.sg-effect-fade.sg-active .sg-modal-content { animation: sg-fade-in 0.3s ease forwards; }
            .sg-modal.sg-effect-fade.sg-closing .sg-modal-content { animation: sg-fade-out 0.2s ease forwards; }
            @keyframes sg-fade-in { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
            @keyframes sg-fade-out { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(0.95); } }
            
            /* 3. 转盘旋转 */
            .sg-modal.sg-effect-rotate.sg-active .sg-modal-content { animation: sg-rotate-in 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55) forwards; }
            .sg-modal.sg-effect-rotate.sg-closing .sg-modal-content { animation: sg-rotate-out 0.3s ease forwards; }
            @keyframes sg-rotate-in { from { opacity: 0; transform: scale(0.5) rotate(-45deg); } to { opacity: 1; transform: scale(1) rotate(0); } }
            @keyframes sg-rotate-out { from { opacity: 1; transform: scale(1) rotate(0); } to { opacity: 0; transform: scale(0.5) rotate(45deg); } }

            /* 4. 卡片翻转 */
            .sg-modal.sg-effect-flip .sg-modal-content { backface-visibility: hidden; }
            .sg-modal.sg-effect-flip.sg-active .sg-modal-content { animation: sg-flip-in 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55) forwards; }
            .sg-modal.sg-effect-flip.sg-closing .sg-modal-content { animation: sg-flip-out 0.4s ease-in forwards; }
            @keyframes sg-flip-in { from { transform: rotateX(-90deg); opacity: 0; } to { transform: rotateX(0); opacity: 1; } }
            @keyframes sg-flip-out { from { transform: rotateX(0); opacity: 1; } to { transform: rotateX(90deg); opacity: 0; } }

            /* 5. 层叠推开 */
            .sg-modal.sg-effect-stack.sg-active .sg-modal-content { animation: sg-stack-in 0.35s cubic-bezier(0.32, 0.72, 0, 1) forwards; }
            .sg-modal.sg-effect-stack.sg-closing .sg-modal-content { animation: sg-stack-out 0.25s ease-in forwards; }
            @keyframes sg-stack-in { from { opacity: 0; transform: translateY(50px) scale(0.9); } to { opacity: 1; transform: translateY(0) scale(1); } }
            @keyframes sg-stack-out { from { opacity: 1; transform: translateY(0) scale(1); } to { opacity: 0; transform: translateY(-50px) scale(0.9); } }

            /* 6. 中心缩放 */
            .sg-modal.sg-effect-zoom.sg-active .sg-modal-content { animation: sg-zoom-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
            .sg-modal.sg-effect-zoom.sg-closing .sg-modal-content { animation: sg-zoom-out 0.2s ease forwards; }
            @keyframes sg-zoom-in { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
            @keyframes sg-zoom-out { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(0.8); } }

            .sg-modal-header { color: #333; display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: #fff; border-bottom: 1px solid #eee; flex-shrink: 0; }
            .sg-modal-header h2 { margin: 0; font-size: 17px; }
            
            .sg-close-btn { width: 26px; height: 26px; background: url(https://www.luohuayu.cn/usr/uploads/2026/03/guanbi.png) no-repeat center center; background-size: contain; cursor: pointer; transition: transform 0.2s; display: block; border: none; outline: none; background-color: transparent; }
            .sg-close-btn:hover { transform: rotate(90deg); }
            
            .sg-body { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 15px; scrollbar-width: thin; scrollbar-color: #ccc transparent; -webkit-overflow-scrolling: touch; }
            .sg-body::-webkit-scrollbar { width: 6px; }
            .sg-body::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
            
            .sg-images-grid { display: grid; grid-gap: 12px; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            @media (min-width: 768px) { .sg-images-grid { grid-template-columns: repeat(' . $pcCols . ', 1fr); } }
            @media (max-width: 767px) { .sg-images-grid { grid-template-columns: repeat(' . $mobileCols . ', 1fr); } }

            .sg-images-masonry { column-count: ' . $pcCols . '; column-gap: 12px; }
            @media (max-width: 767px) { .sg-images-masonry { column-count: ' . $mobileCols . '; } }
            
            .sg-img-item { background: #fff; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; position: relative; margin-bottom: 12px; break-inside: avoid; }
            .sg-img-item:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
            .sg-img-item .img-box { position: relative; overflow: hidden; background: #f5f5f5; }
            .sg-images-grid .sg-img-item .img-box { aspect-ratio: 1; }
            
            .sg-img-item img, .sg-img-item video { width: 100%; height: 100%; object-fit: cover; display: block; opacity: 1 !important; }
            .sg-images-masonry .sg-img-item img, .sg-images-masonry .sg-img-item video { height: auto; }

            .sg-img-desc { padding: 8px 10px; font-size: 12px; color: #555; line-height: 1.5; background: #fafafa; border-top: 1px solid #f0f0f0; }
            .sg-lightbox-desc { position: fixed; bottom: 0; left: 0; right: 0; background: rgba(0,0,0, 0.85); color: #fff; padding: 15px 20px; text-align: center; font-size: 14px; line-height: 1.6; z-index: 99999; display: none; }
            .sg-password-box { text-align: center; padding: 40px 20px; background: #fafafa; border-radius: 8px; }
            .sg-password-box h3 { margin-bottom: 15px; color: #333; font-size: 16px;}
            .sg-password-box .input-group { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
            .sg-password-box input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; min-width: 180px; font-size: 14px; }
            .sg-password-box button { padding: 8px 20px; background: #467B96; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
            .sg-password-box .msg { color: #e74c3c; margin-top: 12px; font-size: 13px; min-height: 18px; }
        </style>';

        $albumSelect = $db->select()->from($prefix . 'smart_gallery_albums');
        if ($targetAlbumId > 0) { $albumSelect->where('id = ?', $targetAlbumId); }
        $albums = $db->fetchAll($albumSelect->order('sort_order', Typecho_Db::SORT_ASC)->order('created', Typecho_Db::SORT_DESC));

        $html = $css . '<div class="sg-album-grid" id="sg-album-list">';

        foreach ($albums as $album) {
            $albumId = $album['id'];
            $isPrivate = isset($album['password']) && !empty($album['password']);
            $description = isset($album['description']) ? $album['description'] : '';
            $layout = isset($album['layout']) ? $album['layout'] : 'grid';
            
            $coverUrl = '';
            if (isset($album['cover']) && !empty($album['cover'])) {
                $coverRaw = $album['cover'];
                if (strpos($coverRaw, 'http://') === 0 || strpos($coverRaw, 'https://') === 0) { $coverUrl = $coverRaw; } 
                else { $coverUrl = $options->siteUrl . 'usr/uploads/' . $coverRaw; }
            } else {
                $coverImg = $db->fetchRow($db->select('filename')->from($prefix . 'smart_gallery_images')->where('album_id = ?', $albumId)->order('sort_order', Typecho_Db::SORT_ASC)->limit(1));
                if ($coverImg) { $coverUrl = $options->siteUrl . 'usr/uploads/' . $coverImg['filename']; }
            }
            if (empty($coverUrl)) { $coverUrl = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 3"%3E%3Crect fill="%23eee" width="4" height="3"/%3E%3C/svg%3E'; }

            $html .= '<div class="sg-album-card" onclick="sgOpenModal('.$albumId.')" data-album-id="' . $albumId . '">
                <div class="sg-album-cover-box"><img src="' . $coverUrl . '" alt="' . htmlspecialchars($album['name']) . '">
                ' . ($isPrivate ? '<div class="sg-lock-icon"></div>' : '') . '
                <div class="sg-album-cover-title">' . htmlspecialchars($album['name']) . '</div></div>
                <div class="sg-album-desc-box"><div class="sg-album-desc">' . ($description ? htmlspecialchars($description) : '<span style="color:#999;font-style:italic;">暂无简介</span>') . '</div></div></div>';

            $layoutClass = ($layout === 'masonry') ? 'sg-images-masonry' : 'sg-images-grid';
            $html .= '<div id="sg-album-'.$albumId.'" class="sg-modal" data-layout="' . $layout . '"><div class="sg-modal-content">
                    <div class="sg-modal-header"><h2>' . htmlspecialchars($album['name']) . '</h2><button class="sg-close-btn" onclick="sgCloseModal(' . $albumId . ', event)"></button></div>
                    <div class="sg-body" id="sg-body-'.$albumId.'" data-layout="' . $layout . '">';
            
            $unlocked = false;
            if ($isPrivate) {
                if (isset($_SESSION['sg_unlocked_'.$albumId]) && $_SESSION['sg_unlocked_'.$albumId] === true) { $unlocked = true; }
            }

            if (!$isPrivate || $unlocked) {
                $html .= '<div class="' . $layoutClass . '" id="sg-grid-'.$albumId.'">';
                $images = $db->fetchAll($db->select()->from($prefix . 'smart_gallery_images')->where('album_id = ?', $albumId)->order('sort_order', Typecho_Db::SORT_ASC));
                foreach ($images as $img) {
                    $imgUrl = $options->siteUrl . 'usr/uploads/' . $img['filename'];
                    $imgDesc = isset($img['description']) ? htmlspecialchars($img['description']) : '';
                    $imgDescData = !empty($imgDesc) ? htmlspecialchars($imgDesc) : '';
                    
                    if (isset($img['type']) && $img['type'] === 'video') {
                        $html .= '<div class="sg-img-item" data-desc="' . $imgDescData . '"><div class="img-box"><video src="' . $imgUrl . '" controls></video></div>';
                        if (!empty($imgDesc)) { $html .= '<div class="sg-img-desc">' . $imgDesc . '</div>'; }
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="sg-img-item" data-desc="' . $imgDescData . '"><div class="img-box"><a href="' . $imgUrl . '" data-fancybox="gallery-' . $albumId . '" data-caption="' . $imgDesc . '"><img src="' . $imgUrl . '"></a></div>';
                        if (!empty($imgDesc)) { $html .= '<div class="sg-img-desc">' . $imgDesc . '</div>'; }
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';
            }
            $html .= '</div></div></div>';
        }

        $html .= '</div><div class="sg-lightbox-desc" id="sg-lightbox-desc"></div>';
        $html .= self::getJS($options, $openEffect);
        
        if ($return) return $html;
        echo $html;
    }

    private static function getJS($options, $openEffect = 'slide') {
        $indexUrl = $options->index;
        $siteUrl = $options->siteUrl;
        
        $js = <<<JSCODE
<script>
var sgIndexUrl = "{$indexUrl}";
var sgSiteUrl = "{$siteUrl}";
var sgCurrentAlbumId = 0;
var sgOpenEffect = "{$openEffect}";
var sgClosingTimeout = null;

function sgOpenModal(id) {
    sgCurrentAlbumId = id;
    var modal = document.getElementById("sg-album-" + id);
    if (modal) {
        if (sgClosingTimeout) { clearTimeout(sgClosingTimeout); sgClosingTimeout = null; }
        
        // 1. 重置所有类名
        modal.classList.remove('sg-active', 'sg-closing', 'sg-effect-slide', 'sg-effect-fade', 'sg-effect-rotate', 'sg-effect-flip', 'sg-effect-stack', 'sg-effect-zoom');
        
        // 2. 添加当前选择的动画效果类
        modal.classList.add('sg-effect-' + sgOpenEffect);
        
        // 3. 强制重绘
        void modal.offsetWidth;
        
        // 4. 激活弹窗
        modal.classList.add('sg-active');
        document.body.style.overflow = "hidden";
        
        var body = document.getElementById("sg-body-" + id);
        if (!body.querySelector(".sg-images-grid") && !body.querySelector(".sg-images-masonry") && !body.querySelector(".sg-password-box")) {
            showPasswordBox(id);
        }
        setTimeout(function() { sgInitLightbox(id); }, 100);
    }
}

function sgInitLightbox(albumId) {
    var body = document.getElementById("sg-body-" + albumId);
    if (!body) return;
    var links = body.querySelectorAll('a[data-fancybox]');
    links.forEach(function(link) {
        link.removeEventListener('click', sgHandleLightboxClick);
        link.addEventListener('click', sgHandleLightboxClick);
    });
}

function sgHandleLightboxClick(e) {
    e.preventDefault(); var link = e.currentTarget; var href = link.getAttribute('href'); var caption = link.getAttribute('data-caption') || '';
    var albumId = sgCurrentAlbumId; var body = document.getElementById("sg-body-" + albumId); var links = body.querySelectorAll('a[data-fancybox]');
    var currentIndex = 0; links.forEach(function(l, i) { if (l === link) currentIndex = i; });
    sgCreateLightbox(href, caption, links, currentIndex);
}

function sgCreateLightbox(src, caption, links, currentIndex) {
    var existing = document.getElementById('sg-custom-lightbox'); if (existing) existing.remove();
    var lightbox = document.createElement('div'); lightbox.id = 'sg-custom-lightbox';
    lightbox.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);z-index:99998;display:flex;align-items:center;justify-content:center;';
    lightbox.innerHTML = '<button onclick="sgCloseLightbox()" style="position:absolute;top:20px;right:20px;width:40px;height:40px;background:rgba(255,255,255,0.1);border:none;border-radius:50%;cursor:pointer;color:#fff;font-size:24px;z-index:99999;">&times;</button><button onclick="sgPrevImage(event)" style="position:absolute;left:20px;top:50%;transform:translateY(-50%);width:50px;height:50px;background:rgba(255,255,255,0.1);border:none;border-radius:50%;cursor:pointer;color:#fff;font-size:20px;z-index:99999;">&#10094;</button><button onclick="sgNextImage(event)" style="position:absolute;right:20px;top:50%;transform:translateY(-50%);width:50px;height:50px;background:rgba(255,255,255,0.1);border:none;border-radius:50%;cursor:pointer;color:#fff;font-size:20px;z-index:99999;">&#10095;</button><img id="sg-lightbox-img" src="' + src + '" style="max-width:90%;max-height:80vh;object-fit:contain;">';
    document.body.appendChild(lightbox); lightbox.dataset.index = currentIndex;
    lightbox.dataset.links = JSON.stringify(Array.from(links).map(function(l) { return { src: l.getAttribute('href'), caption: l.getAttribute('data-caption') || '' }; }));
    sgShowLightboxDesc(caption);
    lightbox.addEventListener('click', function(e) { if (e.target === lightbox) sgCloseLightbox(); });
    document.addEventListener('keydown', sgHandleKeydown);
}

function sgCloseLightbox() { var lightbox = document.getElementById('sg-custom-lightbox'); if (lightbox) lightbox.remove(); var descEl = document.getElementById("sg-lightbox-desc"); if (descEl) descEl.style.display = "none"; document.removeEventListener('keydown', sgHandleKeydown); }
function sgHandleKeydown(e) { if (e.key === 'Escape') sgCloseLightbox(); else if (e.key === 'ArrowLeft') sgPrevImage(e); else if (e.key === 'ArrowRight') sgNextImage(e); }
function sgPrevImage(e) { if(e) e.stopPropagation(); var lightbox = document.getElementById('sg-custom-lightbox'); if (!lightbox) return; var links = JSON.parse(lightbox.dataset.links); var currentIndex = parseInt(lightbox.dataset.index); currentIndex = (currentIndex - 1 + links.length) % links.length; sgUpdateLightboxImage(links[currentIndex], currentIndex); }
function sgNextImage(e) { if(e) e.stopPropagation(); var lightbox = document.getElementById('sg-custom-lightbox'); if (!lightbox) return; var links = JSON.parse(lightbox.dataset.links); var currentIndex = parseInt(lightbox.dataset.index); currentIndex = (currentIndex + 1) % links.length; sgUpdateLightboxImage(links[currentIndex], currentIndex); }
function sgUpdateLightboxImage(linkData, index) { var lightbox = document.getElementById('sg-custom-lightbox'); var img = document.getElementById('sg-lightbox-img'); img.src = linkData.src; lightbox.dataset.index = index; sgShowLightboxDesc(linkData.caption); }
function sgShowLightboxDesc(caption) { var descEl = document.getElementById("sg-lightbox-desc"); if (descEl) { if (caption) { descEl.innerText = caption; descEl.style.display = "block"; } else { descEl.style.display = "none"; } } }
function showPasswordBox(id) { var body = document.getElementById("sg-body-" + id); body.innerHTML = '<div class="sg-password-box"><h3>该相册需要密码访问</h3><div class="input-group"><input type="password" id="sg_pwd_' + id + '" placeholder="请输入访问密码"><button onclick="checkGalleryPwd(' + id + ', event)">解锁</button></div><div class="msg" id="sg_msg_' + id + '"></div></div>'; }

function sgCloseModal(id, e) {
    if(e && e.stopPropagation) { e.stopPropagation(); } if(e && e.preventDefault) { e.preventDefault(); }
    var modal = document.getElementById("sg-album-" + id);
    if (modal && modal.classList.contains('sg-active')) { 
        modal.classList.remove('sg-active');
        modal.classList.add('sg-closing');
        
        sgCloseLightbox(); var descEl = document.getElementById("sg-lightbox-desc"); if (descEl) descEl.style.display = "none";
        
        sgClosingTimeout = setTimeout(function() {
            modal.classList.remove('sg-closing');
            modal.classList.remove('sg-effect-' + sgOpenEffect);
            document.body.style.overflow = "";
            sgClosingTimeout = null;
        }, 400);
    }
}

document.addEventListener("click", function(e){ if(e.target.classList.contains("sg-modal")) { var id = e.target.id.replace('sg-album-', ''); sgCloseModal(id, e); } });

function checkGalleryPwd(id, evt) {
    if(evt && evt.preventDefault) evt.preventDefault(); var pwdInput = document.getElementById("sg_pwd_" + id); var msgDiv = document.getElementById("sg_msg_" + id); var pwd = pwdInput.value; msgDiv.innerText = "验证中...";
    var formData = new FormData(); formData.append("album_id", id); formData.append("password", pwd);
    var xhr = new XMLHttpRequest(); xhr.open("POST", sgIndexUrl + "/action/smart-gallery?do=check-pwd", true);
    xhr.onload = function() {
        try { var data = JSON.parse(xhr.responseText); if(data.status === "success") { msgDiv.innerText = "解锁成功！"; var body = document.getElementById("sg-body-" + id); body.innerHTML = "加载中..."; var imgXhr = new XMLHttpRequest(); imgXhr.open("GET", sgIndexUrl + "/action/smart-gallery?do=fetch-images&album_id=" + id); imgXhr.onload = function() { var images = JSON.parse(imgXhr.responseText); var layout = body.getAttribute("data-layout") || "grid"; var layoutClass = (layout === "masonry") ? "sg-images-masonry" : "sg-images-grid"; var html = '<div class="' + layoutClass + '" id="sg-grid-' + id + '">'; images.forEach(function(img) { var url = sgSiteUrl + "usr/uploads/" + img.filename; var desc = img.description || ""; var descData = desc.replace(/"/g, '&quot;'); if(img.type === "video") { html += '<div class="sg-img-item" data-desc="' + descData + '"><div class="img-box"><video src="' + url + '" controls></video></div>'; if(desc) html += '<div class="sg-img-desc">' + desc + '</div>'; html += '</div>'; } else { html += '<div class="sg-img-item" data-desc="' + descData + '"><div class="img-box"><a href="' + url + '" data-fancybox="gallery-' + id + '" data-caption="' + desc.replace(/"/g, '\\"') + '"><img src="' + url + '"></a></div>'; if(desc) html += '<div class="sg-img-desc">' + desc + '</div>'; html += '</div>'; } }); html += '</div>'; body.innerHTML = html; setTimeout(function() { sgInitLightbox(id); }, 100); }; imgXhr.send(); } else { msgDiv.innerText = "密码错误"; pwdInput.value = ""; } } catch(e) { msgDiv.innerText = "请求失败"; }
    };
    xhr.send(formData);
}

document.addEventListener("keydown", function(e) { if (e.key === "Escape") { document.querySelectorAll(".sg-modal.sg-active").forEach(function(m) { var id = m.id.replace('sg-album-', ''); sgCloseModal(id, e); }); } });
</script>
JSCODE;
        return $js;
    }
}
