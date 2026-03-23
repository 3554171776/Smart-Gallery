<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

/**
 * Smart Gallery 相册插件
 *
 * @package Smart Gallery
 * @author 落花雨记
 * @version 1.3.1
 * @link https://www.luohuayu.cn
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'Action.php';

class SmartGallery_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $scripts = self::getTableScripts($prefix);
        
        foreach ($scripts as $script) {
            try {
                $db->query($script);
            } catch (Exception $e) {
            }
        }
        
        self::upgradeDatabase($db, $prefix);
        
        Helper::addAction('smart-gallery', 'SmartGallery_Action');
        Helper::addPanel(3, 'SmartGallery/Panel.php', '相册管理', '管理图片相册', 'administrator');
        
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array(
            'SmartGallery_Plugin', 'parseShortcode'
        );
        
        return _t('插件启用成功，请配置插件设置。');
    }
    
    private static function getTableScripts($prefix)
    {
        return array(
            "CREATE TABLE IF NOT EXISTS `{$prefix}smart_gallery_albums` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `slug` varchar(100) DEFAULT '',
                `description` varchar(255) DEFAULT '',
                `cover` varchar(500) DEFAULT '',
                `password` varchar(100) DEFAULT '',
                `layout` varchar(20) DEFAULT 'grid',
                `sort_order` int(10) unsigned DEFAULT 0,
                `created` int(10) unsigned DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
            
            "CREATE TABLE IF NOT EXISTS `{$prefix}smart_gallery_images` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `album_id` int(10) unsigned NOT NULL,
                `filename` TEXT NOT NULL,
                `description` varchar(255) DEFAULT '',
                `type` varchar(20) DEFAULT 'image',
                `source_type` varchar(20) DEFAULT 'upload',
                `sort_order` int(10) unsigned DEFAULT 0,
                `created` int(10) unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `album_id` (`album_id`),
                KEY `source_type` (`source_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );
    }
    
    private static function upgradeDatabase($db, $prefix)
    {
        $albumFields = array(
            'layout' => "varchar(20) DEFAULT 'grid'",
            'sort_order' => 'int(10) unsigned DEFAULT 0'
        );
        
        foreach ($albumFields as $field => $definition) {
            try {
                $db->query("ALTER TABLE `{$prefix}smart_gallery_albums` ADD COLUMN `{$field}` {$definition}");
            } catch (Exception $e) {
            }
        }
        
        $imageFields = array(
            'sort_order' => 'int(10) unsigned DEFAULT 0',
            'type' => "varchar(20) DEFAULT 'image'",
            'source_type' => "varchar(20) DEFAULT 'upload'"
        );
        
        foreach ($imageFields as $field => $definition) {
            try {
                $db->query("ALTER TABLE `{$prefix}smart_gallery_images` ADD COLUMN `{$field}` {$definition}");
            } catch (Exception $e) {
            }
        }
        
        try {
            $db->query("ALTER TABLE `{$prefix}smart_gallery_images` MODIFY COLUMN `filename` TEXT NOT NULL");
        } catch (Exception $e) {
        }
        
        try {
            $db->query("ALTER TABLE `{$prefix}smart_gallery_albums` MODIFY COLUMN `cover` varchar(500) DEFAULT ''");
        } catch (Exception $e) {
        }
        
        try {
            $db->query("ALTER TABLE `{$prefix}smart_gallery_images` ADD INDEX `source_type` (`source_type`)");
        } catch (Exception $e) {
        }
        
        try {
            $db->query("UPDATE `{$prefix}smart_gallery_images` SET `source_type` = 'external' WHERE `filename` LIKE 'http://%' OR `filename` LIKE 'https://%'");
        } catch (Exception $e) {
        }
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
        $config = self::getConfig($db, $prefix);
        
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'pcCols',
            null,
            '4',
            _t('PC端每行显示数量'),
            _t('建议 3-6')
        ));
        
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'mobileCols',
            null,
            '2',
            _t('移动端每行显示数量'),
            _t('建议 2-3')
        ));
        
        // 修改：懒加载默认关闭
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'lazyLoad',
            array('1' => _t('开启'), '0' => _t('关闭')),
            '0',
            _t('图片懒加载'),
            _t('开启后图片仅在滚动到可视区域时加载')
        ));
        
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'lazyPlaceholder',
            null,
            '',
            _t('懒加载占位图（可选）'),
            _t('输入图片URL，留空使用默认占位图')
        ));
        
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'lazyThreshold',
            null,
            '100',
            _t('懒加载提前距离（像素）'),
            _t('建议 50-200，值越大提前加载越明显')
        ));
        
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'webp',
            array('1' => _t('开启'), '0' => _t('关闭')),
            '0',
            _t('WebP 自动压缩'),
            _t('上传时自动转换为 WebP 格式')
        ));
        
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'imgQuality',
            null,
            '80',
            _t('图片压缩质量'),
            _t('范围 0-100，建议 75-85')
        ));
        
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select(
            'openEffect',
            array(
                'slide' => '底部滑入',
                'fade' => '淡入淡出',
                'rotate' => '转盘旋转',
                'flip' => '卡片翻转',
                'stack' => '层叠推开',
                'zoom' => '中心缩放'
            ),
            isset($config['openEffect']) ? $config['openEffect'] : 'slide',
            _t('相册打开动画')
        ));
        
        self::renderEnvInfo();
    }
    
    private static function renderEnvInfo()
    {
        $gdStatus = function_exists('gd_info') ? '<span style="color:#28a745;">✔ 已安装</span>' : '<span style="color:#dc3545;">✘ 未安装</span>';
        $webpStatus = function_exists('imagewebp') ? '<span style="color:#28a745;">✔ 支持</span>' : '<span style="color:#dc3545;">✘ 不支持</span>';
        
        $html = '<div style="margin-top:20px;background:#f8f9fa;padding:15px;border-radius:4px;border:1px solid #e9ecef;">
            <h4 style="margin:0 0 10px;font-size:15px;color:#495057;">系统环境检测</h4>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;font-size:13px;">
                <div><strong>GD 图形库：</strong>' . $gdStatus . '</div>
                <div><strong>WebP 支持：</strong>' . $webpStatus . '</div>
            </div>
        </div>';
        
        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html(_t($html));
        echo $layout->render();
    }
    
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
    
    private static function safeUnserialize($value)
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }
        
        $data = @unserialize($value);
        if (is_array($data)) {
            return $data;
        }
        
        $data = @json_decode($value, true);
        if (is_array($data)) {
            return $data;
        }
        
        return null;
    }
    
    private static function getConfig($db, $prefix)
    {
        $default = array(
            'pcCols' => '4',
            'mobileCols' => '2',
            'webp' => '0',
            'imgQuality' => '80',
            'openEffect' => 'slide',
            'lazyLoad' => '0',
            'lazyPlaceholder' => '',
            'lazyThreshold' => '100'
        );
        
        try {
            $row = $db->fetchRow(
                $db->select('value')
                    ->from($prefix . 'options')
                    ->where('name = ?', 'plugin:SmartGallery')
            );
            
            if ($row && !empty($row['value'])) {
                $data = self::safeUnserialize($row['value']);
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        if ($value !== null && $value !== '') {
                            $default[$key] = $value;
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }
        
        return $default;
    }
    
    public static function parseShortcode($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        
        if ($widget instanceof Widget_Archive) {
            $pattern = '/\[gallery\s*(?:id=(\d+))?\]/i';
            $content = preg_replace_callback($pattern, function ($matches) {
                $albumId = isset($matches[1]) && $matches[1] ? intval($matches[1]) : 0;
                return self::output($albumId, true);
            }, $content);
        }
        
        return $content;
    }
    
    /**
     * 增强外链识别 - 支持API链接
     */
    private static function isExternalUrl($url)
    {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        $url = trim($url);
        
        // 支持 http:// 和 https://
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return true;
        }
        
        // 支持 API 链接（如 api.xxx.com/xxx/）
        if (preg_match('/^https?:\/\/[^\/]+\/api\//i', $url)) {
            return true;
        }
        
        return false;
    }
    
    private static function getImageUrl($filename, $siteUrl)
    {
        if (self::isExternalUrl($filename)) {
            return $filename;
        }
        return $siteUrl . 'usr/uploads/' . $filename;
    }
    
    public static function output($targetAlbumId = 0, $return = false)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $options = Typecho_Widget::widget('Widget_Options');
        $siteUrl = $options->siteUrl;
        $config = self::getConfig($db, $prefix);
        
        $pcCols = isset($config['pcCols']) ? intval($config['pcCols']) : 4;
        $mobileCols = isset($config['mobileCols']) ? intval($config['mobileCols']) : 2;
        $openEffect = isset($config['openEffect']) ? $config['openEffect'] : 'slide';
        $lazyLoad = isset($config['lazyLoad']) ? $config['lazyLoad'] : '0';
        $lazyPlaceholder = isset($config['lazyPlaceholder']) ? $config['lazyPlaceholder'] : '';
        $lazyThreshold = isset($config['lazyThreshold']) ? intval($config['lazyThreshold']) : 100;
        
        $html = self::buildStyles($pcCols, $mobileCols);
        $html .= '<div class="sg-album-grid" id="sg-album-list">';
        
        $select = $db->select()->from($prefix . 'smart_gallery_albums');
        if ($targetAlbumId > 0) {
            $select->where('id = ?', $targetAlbumId);
        }
        
        $albums = $db->fetchAll(
            $select->order('sort_order', Typecho_Db::SORT_ASC)->order('created', Typecho_Db::SORT_DESC)
        );
        
        foreach ($albums as $album) {
            $html .= self::buildAlbumCard($album, $db, $prefix, $siteUrl, $lazyLoad, $lazyPlaceholder);
        }
        
        $html .= '</div>';
        $html .= '<div class="sg-lightbox-desc" id="sg-lightbox-desc"></div>';
        $html .= self::buildScripts($options, $openEffect, $lazyLoad, $lazyPlaceholder, $lazyThreshold);
        
        if ($return) {
            return $html;
        }
        
        echo $html;
    }
    
    private static function buildStyles($pcCols, $mobileCols)
    {
        return '<style>
.sg-album-grid {
    display: grid;
    grid-gap: 25px;
    margin: 20px 0;
}

@media (min-width: 768px) {
    .sg-album-grid {
        grid-template-columns: repeat(' . $pcCols . ', 1fr);
    }
}

@media (max-width: 767px) {
    .sg-album-grid {
        grid-template-columns: repeat(' . $mobileCols . ', 1fr);
    }
}

.sg-album-card {
    overflow: hidden;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    position: relative;
    background-color: #fff;
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
    cursor: pointer;
}

.sg-album-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.12);
}

.sg-album-cover-box {
    position: relative;
    aspect-ratio: 4/3;
    overflow: hidden;
    background: #f5f5f5;
}

.sg-album-cover-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.5s;
    pointer-events: none;
}

.sg-album-card:hover .sg-album-cover-box img {
    transform: scale(1.05);
}

.sg-lock-icon {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;
    width: 36px;
    height: 36px;
    background: url(https://www.luohuayu.cn/usr/uploads/SmartGallery/2026/03/q90_1774170978813.webp) no-repeat center center;
    background-size: contain;
    pointer-events: none;
}

.sg-album-cover-title {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 2;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: #fff;
    padding: 30px 15px 10px;
    font-size: 16px;
    font-weight: bold;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    pointer-events: none;
}

.sg-album-desc-box {
    padding: 12px 15px 15px;
    background: #fff;
}

.sg-album-desc {
    font-size: 13px;
    color: #666;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* 弹窗样式 */
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
    transition: background 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    perspective: 1200px;
}

.sg-modal.sg-active {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.85);
}

.sg-modal.sg-closing {
    background: rgba(0,0,0,0);
}

.sg-modal-content {
    width: 88%;
    max-width: 880px;
    max-height: 82vh;
    background: #fff;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 80px rgba(0,0,0,0.5);
    will-change: transform, opacity;
    transform-style: preserve-3d;
    overflow: hidden;
}

.sg-modal-header {
    color: #333;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    background: #fff;
    border-bottom: 1px solid #eee;
    flex-shrink: 0;
    border-radius: 10px 10px 0 0;
}

.sg-modal-header h2 {
    margin: 0;
    font-size: 17px;
}

.sg-close-btn {
    width: 28px;
    height: 28px;
    background: url(https://www.luohuayu.cn/usr/uploads/2026/03/guanbi.png) no-repeat center center;
    background-size: contain;
    cursor: pointer;
    transition: transform 0.2s, opacity 0.2s;
    display: block;
    border: none;
    outline: none;
    background-color: transparent;
    opacity: 0.7;
}

.sg-close-btn:hover {
    transform: rotate(90deg);
    opacity: 1;
}

.sg-body {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 15px;
    scrollbar-width: thin;
    scrollbar-color: #ccc transparent;
    -webkit-overflow-scrolling: touch;
    background: #fff;
    position: relative;
}

.sg-body::-webkit-scrollbar {
    width: 6px;
}

.sg-body::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 3px;
}

.sg-images-grid {
    display: grid;
    grid-gap: 12px;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
}

@media (min-width: 768px) {
    .sg-images-grid {
        grid-template-columns: repeat(' . $pcCols . ', 1fr);
    }
}

@media (max-width: 767px) {
    .sg-images-grid {
        grid-template-columns: repeat(' . $mobileCols . ', 1fr);
    }
}

.sg-images-masonry {
    column-count: ' . $pcCols . ';
    column-gap: 12px;
}

@media (max-width: 767px) {
    .sg-images-masonry {
        column-count: ' . $mobileCols . ';
    }
}

/* 图片项样式 */
.sg-img-item {
    background: #fff;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: transform 0.3s, box-shadow 0.3s, opacity 0.4s ease;
    position: relative;
    margin-bottom: 12px;
    break-inside: avoid;
    opacity: 0;
    transform: translateY(15px);
}

.sg-img-item.sg-visible {
    opacity: 1;
    transform: translateY(0);
}

.sg-img-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.12);
}

.sg-img-item .img-box {
    position: relative;
    overflow: hidden;
    background: #f5f5f5;
}

.sg-images-grid .sg-img-item .img-box {
    aspect-ratio: 1;
}

.sg-img-item img,
.sg-img-item video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.sg-images-masonry .sg-img-item img,
.sg-images-masonry .sg-img-item video {
    height: auto;
}

.sg-images-masonry .sg-lazy-img {
    min-height: 150px;
    opacity: 0.3;
}

.sg-lazy-img {
    background: #f5f5f5;
    transition: opacity 0.3s ease;
}

.sg-lazy-img.sg-loaded {
    opacity: 1;
}

.sg-lazy-img.sg-loading {
    opacity: 0.3;
}

.sg-lazy-img.sg-error {
    opacity: 0.3;
    filter: grayscale(100%);
}

.sg-img-desc {
    padding: 8px 10px;
    font-size: 12px;
    color: #555;
    line-height: 1.5;
    background: #fafafa;
    border-top: 1px solid #f0f0f0;
}

.sg-lightbox-desc {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0,0,0,0.85);
    color: #fff;
    padding: 15px 20px;
    text-align: center;
    font-size: 14px;
    line-height: 1.6;
    z-index: 99999;
    display: none;
}

/* 私密相册密码框 */
.sg-password-box {
    text-align: center;
    padding: 40px 20px;
    background: #fafafa;
    border-radius: 8px;
    width: 100%;
}

.sg-password-box h3 {
    margin-bottom: 15px;
    color: #333;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.sg-pwd-icon {
    width: 24px;
    height: 24px;
    background: url(https://www.luohuayu.cn/usr/uploads/SmartGallery/2026/03/q90_1774170978813.webp) no-repeat center center;
    background-size: contain;
    flex-shrink: 0;
}

.sg-password-box .input-group {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}

.sg-password-box input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    min-width: 180px;
    font-size: 14px;
    background: #fff;
    color: #333;
}

.sg-password-box button {
    padding: 8px 20px;
    background: #467B96;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.sg-password-box .msg {
    color: #e74c3c;
    margin-top: 12px;
    font-size: 13px;
    min-height: 18px;
}

/* 动画效果 */
.sg-modal.sg-effect-slide.sg-active .sg-modal-content {
    animation: sg-slide-up 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.sg-modal.sg-effect-slide.sg-closing .sg-modal-content {
    animation: sg-slide-down 0.25s cubic-bezier(0.4, 0, 1, 1) forwards;
}

@keyframes sg-slide-up {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes sg-slide-down {
    from { transform: translateY(0); opacity: 1; }
    to { transform: translateY(100%); opacity: 0; }
}

.sg-modal.sg-effect-fade.sg-active .sg-modal-content {
    animation: sg-fade-in 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.sg-modal.sg-effect-fade.sg-closing .sg-modal-content {
    animation: sg-fade-out 0.2s cubic-bezier(0.4, 0, 1, 1) forwards;
}

@keyframes sg-fade-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes sg-fade-out {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.95); }
}

.sg-modal.sg-effect-rotate.sg-active .sg-modal-content {
    animation: sg-rotate-in 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

.sg-modal.sg-effect-rotate.sg-closing .sg-modal-content {
    animation: sg-rotate-out 0.25s cubic-bezier(0.4, 0, 1, 1) forwards;
}

@keyframes sg-rotate-in {
    from { opacity: 0; transform: scale(0.5) rotate(-45deg); }
    to { opacity: 1; transform: scale(1) rotate(0); }
}

@keyframes sg-rotate-out {
    from { opacity: 1; transform: scale(1) rotate(0); }
    to { opacity: 0; transform: scale(0.5) rotate(45deg); }
}

.sg-modal.sg-effect-flip .sg-modal-content {
    backface-visibility: hidden;
}

.sg-modal.sg-effect-flip.sg-active .sg-modal-content {
    animation: sg-flip-in 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

.sg-modal.sg-effect-flip.sg-closing .sg-modal-content {
    animation: sg-flip-out 0.3s cubic-bezier(0.4, 0, 1, 1) forwards;
}

@keyframes sg-flip-in {
    from { transform: rotateX(-90deg); opacity: 0; }
    to { transform: rotateX(0); opacity: 1; }
}

@keyframes sg-flip-out {
    from { transform: rotateX(0); opacity: 1; }
    to { transform: rotateX(90deg); opacity: 0; }
}

.sg-modal.sg-effect-stack.sg-active .sg-modal-content {
    animation: sg-stack-in 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

.sg-modal.sg-effect-stack.sg-closing .sg-modal-content {
    animation: sg-stack-out 0.2s cubic-bezier(0.4, 0, 1, 1) forwards;
}

@keyframes sg-stack-in {
    from { opacity: 0; transform: translateY(50px) scale(0.9); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes sg-stack-out {
    from { opacity: 1; transform: translateY(0) scale(1); }
    to { opacity: 0; transform: translateY(-50px) scale(0.9); }
}

.sg-modal.sg-effect-zoom.sg-active .sg-modal-content {
    animation: sg-zoom-in 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

.sg-modal.sg-effect-zoom.sg-closing .sg-modal-content {
    animation: sg-zoom-out 0.2s cubic-bezier(0.4, 0, 1, 1) forwards;
}

@keyframes sg-zoom-in {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes sg-zoom-out {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.8); }
}

/* ========== 夜间模式适配 ========== */
@media (prefers-color-scheme: dark) {
    .sg-album-card {
        background-color: transparent;
        box-shadow: 0 2px 12px rgba(0,0,0,0.2);
        border: 1px solid rgba(255,255,255,0.08);
    }
    .sg-album-desc-box {
        background: transparent;
    }
    .sg-album-desc {
        color: rgba(255,255,255,0.7);
    }
    .sg-modal.sg-active {
        background: rgba(0,0,0,0.75);
    }
    .sg-modal-content {
        background: rgba(30,30,30,0.95);
        backdrop-filter: blur(10px);
    }
    .sg-modal-header {
        background: transparent;
        border-bottom-color: rgba(255,255,255,0.1);
    }
    .sg-modal-header h2 {
        color: #fff;
    }
    .sg-close-btn {
        filter: invert(1);
        opacity: 0.6;
    }
    .sg-close-btn:hover {
        opacity: 1;
    }
    .sg-body {
        background: transparent;
    }
    .sg-body::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.2);
    }
    .sg-img-item {
        background: transparent;
        border: 1px solid rgba(255,255,255,0.08);
    }
    .sg-img-item .img-box {
        background: rgba(128,128,128,0.1);
    }
    .sg-img-desc {
        background: rgba(0,0,0,0.4);
        border-top-color: rgba(255,255,255,0.08);
        color: rgba(255,255,255,0.8);
    }
    .sg-password-box {
        background: transparent;
    }
    .sg-password-box h3 {
        color: #fff;
    }
    .sg-password-box input {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.2);
        color: #fff;
    }
    .sg-password-box input::placeholder {
        color: rgba(255,255,255,0.4);
    }
    .sg-password-box .msg {
        color: rgba(255,150,150,0.9);
    }
}

/* class类名夜间模式 */
html.dark .sg-album-card,
body.dark .sg-album-card,
html.night .sg-album-card,
body.night .sg-album-card,
html.dark-mode .sg-album-card,
body.dark-mode .sg-album-card {
    background-color: transparent;
    box-shadow: 0 2px 12px rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.08);
}

html.dark .sg-album-desc-box,
body.dark .sg-album-desc-box,
html.night .sg-album-desc-box,
body.night .sg-album-desc-box,
html.dark-mode .sg-album-desc-box,
body.dark-mode .sg-album-desc-box {
    background: transparent;
}

html.dark .sg-album-desc,
body.dark .sg-album-desc,
html.night .sg-album-desc,
body.night .sg-album-desc,
html.dark-mode .sg-album-desc,
body.dark-mode .sg-album-desc {
    color: rgba(255,255,255,0.7);
}

html.dark .sg-modal.sg-active,
body.dark .sg-modal.sg-active,
html.night .sg-modal.sg-active,
body.night .sg-modal.sg-active,
html.dark-mode .sg-modal.sg-active,
body.dark-mode .sg-modal.sg-active {
    background: rgba(0,0,0,0.75);
}

html.dark .sg-modal-content,
body.dark .sg-modal-content,
html.night .sg-modal-content,
body.night .sg-modal-content,
html.dark-mode .sg-modal-content,
body.dark-mode .sg-modal-content {
    background: rgba(30,30,30,0.95);
    backdrop-filter: blur(10px);
}

html.dark .sg-modal-header,
body.dark .sg-modal-header,
html.night .sg-modal-header,
body.night .sg-modal-header,
html.dark-mode .sg-modal-header,
body.dark-mode .sg-modal-header {
    background: transparent;
    border-bottom-color: rgba(255,255,255,0.1);
}

html.dark .sg-modal-header h2,
body.dark .sg-modal-header h2,
html.night .sg-modal-header h2,
body.night .sg-modal-header h2,
html.dark-mode .sg-modal-header h2,
body.dark-mode .sg-modal-header h2 {
    color: #fff;
}

html.dark .sg-close-btn,
body.dark .sg-close-btn,
html.night .sg-close-btn,
body.night .sg-close-btn,
html.dark-mode .sg-close-btn,
body.dark-mode .sg-close-btn {
    filter: invert(1);
    opacity: 0.6;
}

html.dark .sg-close-btn:hover,
body.dark .sg-close-btn:hover,
html.night .sg-close-btn:hover,
body.night .sg-close-btn:hover,
html.dark-mode .sg-close-btn:hover,
body.dark-mode .sg-close-btn:hover {
    opacity: 1;
}

html.dark .sg-body,
body.dark .sg-body,
html.night .sg-body,
body.night .sg-body,
html.dark-mode .sg-body,
body.dark-mode .sg-body {
    background: transparent;
}

html.dark .sg-body::-webkit-scrollbar-thumb,
body.dark .sg-body::-webkit-scrollbar-thumb,
html.night .sg-body::-webkit-scrollbar-thumb,
body.night .sg-body::-webkit-scrollbar-thumb,
html.dark-mode .sg-body::-webkit-scrollbar-thumb,
body.dark-mode .sg-body::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
}

html.dark .sg-img-item,
body.dark .sg-img-item,
html.night .sg-img-item,
body.night .sg-img-item,
html.dark-mode .sg-img-item,
body.dark-mode .sg-img-item {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.08);
}

html.dark .sg-img-item .img-box,
body.dark .sg-img-item .img-box,
html.night .sg-img-item .img-box,
body.night .sg-img-item .img-box,
html.dark-mode .sg-img-item .img-box,
body.dark-mode .sg-img-item .img-box {
    background: rgba(128,128,128,0.1);
}

html.dark .sg-img-desc,
body.dark .sg-img-desc,
html.night .sg-img-desc,
body.night .sg-img-desc,
html.dark-mode .sg-img-desc,
body.dark-mode .sg-img-desc {
    background: rgba(0,0,0,0.4);
    border-top-color: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.8);
}

html.dark .sg-password-box,
body.dark .sg-password-box,
html.night .sg-password-box,
body.night .sg-password-box,
html.dark-mode .sg-password-box,
body.dark-mode .sg-password-box {
    background: transparent;
}

html.dark .sg-password-box h3,
body.dark .sg-password-box h3,
html.night .sg-password-box h3,
body.night .sg-password-box h3,
html.dark-mode .sg-password-box h3,
body.dark-mode .sg-password-box h3 {
    color: #fff;
}

html.dark .sg-password-box input,
body.dark .sg-password-box input,
html.night .sg-password-box input,
body.night .sg-password-box input,
html.dark-mode .sg-password-box input,
body.dark-mode .sg-password-box input {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.2);
    color: #fff;
}

html.dark .sg-password-box input::placeholder,
body.dark .sg-password-box input::placeholder,
html.night .sg-password-box input::placeholder,
body.night .sg-password-box input::placeholder,
html.dark-mode .sg-password-box input::placeholder,
body.dark-mode .sg-password-box input::placeholder {
    color: rgba(255,255,255,0.4);
}

html.dark .sg-password-box .msg,
body.dark .sg-password-box .msg,
html.night .sg-password-box .msg,
body.night .sg-password-box .msg,
html.dark-mode .sg-password-box .msg,
body.dark-mode .sg-password-box .msg {
    color: rgba(255,150,150,0.9);
}
</style>';
    }
    
    private static function buildAlbumCard($album, $db, $prefix, $siteUrl, $lazyLoad, $lazyPlaceholder)
    {
        $albumId = $album['id'];
        $isPrivate = !empty($album['password']);
        $description = isset($album['description']) ? $album['description'] : '';
        $layout = isset($album['layout']) ? $album['layout'] : 'grid';
        $coverUrl = self::getAlbumCover($album, $db, $prefix, $siteUrl);
        
        $html = '<div class="sg-album-card" onclick="sgOpenModal(' . $albumId . ')" data-album-id="' . $albumId . '">
    <div class="sg-album-cover-box">
        <img src="' . $coverUrl . '" alt="' . htmlspecialchars($album['name']) . '">
        ' . ($isPrivate ? '<div class="sg-lock-icon"></div>' : '') . '
        <div class="sg-album-cover-title">' . htmlspecialchars($album['name']) . '</div>
    </div>
    <div class="sg-album-desc-box">
        <div class="sg-album-desc">' . ($description ? htmlspecialchars($description) : '<span style="opacity:0.5;font-style:italic;">暂无简介</span>') . '</div>
    </div>
</div>';
        
        $html .= '<div id="sg-album-' . $albumId . '" class="sg-modal" data-layout="' . $layout . '" data-private="' . ($isPrivate ? '1' : '0') . '">
    <div class="sg-modal-content">
        <div class="sg-modal-header">
            <h2>' . htmlspecialchars($album['name']) . '</h2>
            <button class="sg-close-btn" onclick="sgCloseModal(' . $albumId . ', event)"></button>
        </div>
        <div class="sg-body" id="sg-body-' . $albumId . '" data-layout="' . $layout . '" data-loaded="0" data-lazy="' . $lazyLoad . '" data-placeholder="' . htmlspecialchars($lazyPlaceholder) . '"></div>
    </div>
</div>';
        
        return $html;
    }
    
    private static function getAlbumCover($album, $db, $prefix, $siteUrl)
    {
        if (!empty($album['cover'])) {
            return self::getImageUrl($album['cover'], $siteUrl);
        }
        
        $coverImg = $db->fetchRow(
            $db->select('filename')
                ->from($prefix . 'smart_gallery_images')
                ->where('album_id = ?', $album['id'])
                ->order('sort_order', Typecho_Db::SORT_ASC)
                ->limit(1)
        );
        
        if ($coverImg) {
            return self::getImageUrl($coverImg['filename'], $siteUrl);
        }
        
        return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0IDMiPjxyZWN0IGZpbGw9IiMzMzMiIHdpZHRoPSI0IiBoZWlnaHQ9IjMiLz48L3N2Zz4=';
    }
    
    /**
     * 构建 JavaScript - 深度优化版
     */
    private static function buildScripts($options, $openEffect, $lazyLoad, $lazyPlaceholder, $lazyThreshold)
    {
        $indexUrl = $options->index;
        $siteUrl = $options->siteUrl;
        
        $defaultPlaceholder = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0IDMiPjxyZWN0IGZpbGw9IiNkZGQiIHdpZHRoPSI0IiBoZWlnaHQ9IjMiLz48L3N2Zz4=';
        $finalPlaceholder = (!empty($lazyPlaceholder)) ? $lazyPlaceholder : $defaultPlaceholder;
        $jsPlaceholder = addslashes($finalPlaceholder);
        
        return <<<JSSCRIPT
<script>
(function() {
    'use strict';
    
    var SG_CONFIG = {
        indexUrl: "{$indexUrl}",
        siteUrl: "{$siteUrl}",
        openEffect: "{$openEffect}",
        lazyLoad: "{$lazyLoad}",
        lazyThreshold: {$lazyThreshold},
        lazyPlaceholder: "{$jsPlaceholder}",
        renderBatchSize: 15,
        loadBatchSize: 3
    };
    
    var SG_STATE = {
        currentAlbumId: 0,
        closingTimeout: null,
        lazyObserver: null,
        visibilityObserver: null,
        lightboxListener: false,
        loadQueue: [],
        isProcessingQueue: false,
        eventListeners: {},
        isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
    };
    
    function isExternalUrl(url) {
        if (!url || typeof url !== 'string') return false;
        url = url.trim();
        if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0) return true;
        if (/^https?:\/\/[^\/]+\/api\//i.test(url)) return true;
        return false;
    }
    
    function getImageUrl(filename) {
        return isExternalUrl(filename) ? filename : SG_CONFIG.siteUrl + "usr/uploads/" + filename;
    }
    
    function processLoadQueue() {
        if (SG_STATE.isProcessingQueue || SG_STATE.loadQueue.length === 0) return;
        SG_STATE.isProcessingQueue = true;
        var batch = SG_STATE.loadQueue.splice(0, SG_CONFIG.loadBatchSize);
        batch.forEach(function(item, index) {
            setTimeout(function() { loadImage(item.element, item.src); }, index * 30);
        });
        setTimeout(function() {
            SG_STATE.isProcessingQueue = false;
            if (SG_STATE.loadQueue.length > 0) processLoadQueue();
        }, batch.length * 30 + 30);
    }
    
    function loadImage(img, src) {
        if (!img || img.classList.contains('sg-loaded') || img.classList.contains('sg-loading')) return;
        img.classList.add('sg-loading');
        var tempImg = new Image();
        tempImg.onload = function() {
            img.src = src;
            img.classList.remove('sg-loading');
            img.classList.add('sg-loaded');
            tempImg = null;
        };
        tempImg.onerror = function() {
            img.classList.remove('sg-loading');
            img.classList.add('sg-error');
            tempImg = null;
        };
        tempImg.src = src;
    }
    
    function initLazyLoad(body) {
        var container = body || document;
        var lazyImages = container.querySelectorAll('.sg-lazy-img[data-src]:not(.sg-loaded):not(.sg-loading)');
        if (lazyImages.length === 0) return;
        if (SG_STATE.lazyObserver) SG_STATE.lazyObserver.disconnect();
        if ('IntersectionObserver' in window) {
            SG_STATE.lazyObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        var src = img.getAttribute('data-src');
                        if (src) {
                            SG_STATE.loadQueue.push({ element: img, src: src });
                            img.removeAttribute('data-src');
                        }
                        SG_STATE.lazyObserver.unobserve(img);
                    }
                });
                processLoadQueue();
            }, { rootMargin: SG_CONFIG.lazyThreshold + 'px 0px', threshold: 0 });
            lazyImages.forEach(function(img) { SG_STATE.lazyObserver.observe(img); });
        } else {
            Array.from(lazyImages).forEach(function(img) {
                var src = img.getAttribute('data-src');
                if (src) loadImage(img, src);
            });
        }
    }
    
    function initVisibilityObserver(body) {
        var items = body.querySelectorAll('.sg-img-item:not(.sg-visible)');
        if (items.length === 0) return;
        if (SG_STATE.visibilityObserver) SG_STATE.visibilityObserver.disconnect();
        if ('IntersectionObserver' in window) {
            SG_STATE.visibilityObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('sg-visible');
                        SG_STATE.visibilityObserver.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '50px 0px', threshold: 0 });
            items.forEach(function(item) { SG_STATE.visibilityObserver.observe(item); });
        } else {
            items.forEach(function(item) { item.classList.add('sg-visible'); });
        }
    }
    
    function openModal(id) {
        SG_STATE.currentAlbumId = id;
        var modal = document.getElementById('sg-album-' + id);
        if (!modal) return;
        
        if (SG_STATE.closingTimeout) {
            clearTimeout(SG_STATE.closingTimeout);
            SG_STATE.closingTimeout = null;
        }
        
        modal.classList.remove('sg-active', 'sg-closing');
        modal.classList.remove('sg-effect-slide', 'sg-effect-fade', 'sg-effect-rotate', 'sg-effect-flip', 'sg-effect-stack', 'sg-effect-zoom');
        modal.classList.add('sg-effect-' + SG_CONFIG.openEffect);
        void modal.offsetWidth;
        modal.classList.add('sg-active');
        document.body.style.overflow = 'hidden';
        
        var body = document.getElementById('sg-body-' + id);
        if (!body) return;
        
        var isPrivate = modal.getAttribute('data-private') === '1';
        if (isPrivate) {
            var unlocked = sessionStorage.getItem('sg_unlocked_' + id);
            if (!unlocked) {
                showPasswordBox(id, body);
                return;
            }
        }
        
        var isLoaded = body.getAttribute('data-loaded') === '1';
        if (!isLoaded) {
            body.innerHTML = '<div style="text-align:center;padding:40px;color:#999;"><span style="display:inline-block;width:20px;height:20px;border:2px solid #ddd;border-top-color:#467B96;border-radius:50%;animation:sg-spin 0.8s linear infinite;margin-right:8px;vertical-align:middle;"></span>加载中...</div><style>@keyframes sg-spin{to{transform:rotate(360deg);}}</style>';
            setTimeout(function() { loadImages(id); }, 300);
        } else {
            requestAnimationFrame(function() {
                initLazyLoad(body);
                initVisibilityObserver(body);
                initLightbox(id);
            });
        }
    }
    
    function showPasswordBox(albumId, body) {
        body.innerHTML = '<div class="sg-password-box">' +
            '<h3><div class="sg-pwd-icon"></div>此相册已加密</h3>' +
            '<div class="input-group">' +
            '<input type="password" id="sg-pwd-input-' + albumId + '" placeholder="请输入访问密码" onkeypress="if(event.key===\'Enter\') sgCheckPwd(' + albumId + ')">' +
            '<button onclick="sgCheckPwd(' + albumId + ')">解锁</button>' +
            '</div>' +
            '<div class="msg" id="sg-pwd-msg-' + albumId + '"></div>' +
            '</div>';
        setTimeout(function() {
            var input = document.getElementById('sg-pwd-input-' + albumId);
            if (input) input.focus();
        }, 100);
    }
    
    window.sgCheckPwd = function(albumId) {
        var pwdInput = document.getElementById('sg-pwd-input-' + albumId);
        var msgEl = document.getElementById('sg-pwd-msg-' + albumId);
        var password = pwdInput ? pwdInput.value : '';
        if (!password) { msgEl.innerText = '请输入密码'; return; }
        msgEl.innerText = '验证中...';
        fetch(SG_CONFIG.indexUrl + '/action/smart-gallery?do=check-pwd', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ album_id: albumId, password: password })
        }).then(function(res) { return res.json(); }).then(function(data) {
            if (data.status === 'success') {
                sessionStorage.setItem('sg_unlocked_' + albumId, '1');
                msgEl.innerText = '解锁成功';
                var body = document.getElementById('sg-body-' + albumId);
                body.setAttribute('data-loaded', '0');
                body.innerHTML = '<div style="text-align:center;padding:40px;color:#999;"><span style="display:inline-block;width:20px;height:20px;border:2px solid #ddd;border-top-color:#467B96;border-radius:50%;animation:sg-spin 0.8s linear infinite;margin-right:8px;vertical-align:middle;"></span>加载中...</div><style>@keyframes sg-spin{to{transform:rotate(360deg);}}</style>';
                setTimeout(function() { loadImages(albumId); }, 300);
            } else {
                msgEl.innerText = '密码错误，请重试';
                pwdInput.value = '';
                pwdInput.focus();
            }
        }).catch(function() { msgEl.innerText = '验证失败，请稍后重试'; });
    };
    
    function closeModal(id, event) {
        if (event) { event.stopPropagation(); event.preventDefault(); }
        var modal = document.getElementById('sg-album-' + id);
        if (!modal || !modal.classList.contains('sg-active')) return;
        modal.classList.remove('sg-active');
        modal.classList.add('sg-closing');
        closeLightbox();
        SG_STATE.loadQueue = [];
        SG_STATE.isProcessingQueue = false;
        if (SG_STATE.lazyObserver) SG_STATE.lazyObserver.disconnect();
        if (SG_STATE.visibilityObserver) SG_STATE.visibilityObserver.disconnect();
        cleanupEventListeners(id);
        var body = document.getElementById('sg-body-' + id);
        if (body) {
            setTimeout(function() {
                body.innerHTML = '';
                body.setAttribute('data-loaded', '0');
            }, 100);
        }
        SG_STATE.closingTimeout = setTimeout(function() {
            modal.classList.remove('sg-closing');
            modal.classList.remove('sg-effect-' + SG_CONFIG.openEffect);
            document.body.style.overflow = '';
            SG_STATE.closingTimeout = null;
        }, 250);
    }
    
    function cleanupEventListeners(albumId) {
        var key = 'lightbox_' + albumId;
        if (SG_STATE.eventListeners[key]) {
            SG_STATE.eventListeners[key].forEach(function(listener) {
                listener.element.removeEventListener('click', listener.handler);
            });
            delete SG_STATE.eventListeners[key];
        }
    }
    
    function loadImages(id) {
        var body = document.getElementById('sg-body-' + id);
        if (!body) return;
        var layout = body.getAttribute('data-layout') || 'grid';
        var lazyLoad = body.getAttribute('data-lazy') || '0';
        var customPlaceholder = body.getAttribute('data-placeholder') || '';
        var placeholder = customPlaceholder || SG_CONFIG.lazyPlaceholder;
        
        var xhr = new XMLHttpRequest();
        xhr.open('GET', SG_CONFIG.indexUrl + '/action/smart-gallery?do=fetch-images&album_id=' + id, true);
        xhr.onload = function() {
            try {
                var images = JSON.parse(xhr.responseText);
                var layoutClass = (layout === 'masonry') ? 'sg-images-masonry' : 'sg-images-grid';
                if (images.length === 0) {
                    body.innerHTML = '<div style="text-align:center;color:#999;padding:40px;">暂无图片</div>';
                    body.setAttribute('data-loaded', '1');
                    return;
                }
                var container = document.createElement('div');
                container.className = layoutClass;
                container.id = 'sg-grid-' + id;
                body.innerHTML = '';
                body.appendChild(container);
                var batchSize = SG_STATE.isMobile ? 10 : SG_CONFIG.renderBatchSize;
                var currentIndex = 0;
                
                function renderBatch() {
                    var fragment = document.createDocumentFragment();
                    var end = Math.min(currentIndex + batchSize, images.length);
                    for (var i = currentIndex; i < end; i++) {
                        var img = images[i];
                        var url = getImageUrl(img.filename);
                        var desc = img.description || '';
                        var item = document.createElement('div');
                        item.className = 'sg-img-item';
                        var itemHtml = '';
                        if (img.type === 'video') {
                            itemHtml = '<div class="img-box"><video src="' + url + '" controls preload="metadata"></video></div>';
                            if (desc) itemHtml += '<div class="sg-img-desc">' + desc + '</div>';
                        } else {
                            var imgTag = (lazyLoad === '1') ? '<img class="sg-lazy-img" src="' + placeholder + '" data-src="' + url + '">' : '<img src="' + url + '">';
                            itemHtml = '<div class="img-box"><a href="' + url + '" data-fancybox="gallery-' + id + '" data-caption="' + desc + '">' + imgTag + '</a></div>';
                            if (desc) itemHtml += '<div class="sg-img-desc">' + desc + '</div>';
                        }
                        item.innerHTML = itemHtml;
                        fragment.appendChild(item);
                    }
                    container.appendChild(fragment);
                    currentIndex = end;
                    if (currentIndex < images.length) {
                        requestAnimationFrame(renderBatch);
                    } else {
                        body.setAttribute('data-loaded', '1');
                        requestAnimationFrame(function() {
                            initVisibilityObserver(body);
                            if (lazyLoad === '1') initLazyLoad(body);
                            initLightbox(id);
                        });
                    }
                }
                renderBatch();
            } catch (e) {
                body.innerHTML = '<p style="text-align:center;color:#999;padding:40px;">加载失败</p>';
            }
        };
        xhr.send();
    }
    
    function initLightbox(albumId) {
        var body = document.getElementById('sg-body-' + albumId);
        if (!body) return;
        var links = body.querySelectorAll('a[data-fancybox]');
        var listeners = [];
        links.forEach(function(link) {
            var handler = function(e) { handleLightboxClick(e, albumId); };
            link.addEventListener('click', handler);
            listeners.push({ element: link, handler: handler });
        });
        SG_STATE.eventListeners['lightbox_' + albumId] = listeners;
    }
    
    function handleLightboxClick(event, albumId) {
        event.preventDefault();
        var link = event.currentTarget;
        var href = link.getAttribute('href');
        var caption = link.getAttribute('data-caption') || '';
        var body = document.getElementById('sg-body-' + albumId);
        var links = body.querySelectorAll('a[data-fancybox]');
        var index = 0;
        links.forEach(function(l, i) { if (l === link) index = i; });
        createLightbox(href, caption, links, index);
    }
    
    function createLightbox(src, caption, links, index) {
        closeLightbox();
        var lightbox = document.createElement('div');
        lightbox.id = 'sg-custom-lightbox';
        lightbox.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.92);z-index:99998;display:flex;align-items:center;justify-content:center;';
        lightbox.innerHTML = '<button onclick="sgCloseLightbox()" style="position:absolute;top:20px;right:20px;width:40px;height:40px;background:rgba(255,255,255,0.1);border:none;border-radius:50%;cursor:pointer;color:#fff;font-size:24px;z-index:99999;">×</button>' +
            '<button onclick="sgPrevImg()" style="position:absolute;left:20px;top:50%;transform:translateY(-50%);width:50px;height:50px;background:rgba(255,255,255,0.1);border:none;border-radius:50%;cursor:pointer;color:#fff;font-size:20px;z-index:99999;">‹</button>' +
            '<button onclick="sgNextImg()" style="position:absolute;right:20px;top:50%;transform:translateY(-50%);width:50px;height:50px;background:rgba(255,255,255,0.1);border:none;border-radius:50%;cursor:pointer;color:#fff;font-size:20px;z-index:99999;">›</button>' +
            '<img id="sg-lightbox-img" src="' + src + '" style="max-width:90%;max-height:80vh;object-fit:contain;">';
        document.body.appendChild(lightbox);
        lightbox.dataset.index = index;
        lightbox.dataset.links = JSON.stringify(Array.from(links).map(function(l) {
            return { src: l.getAttribute('href'), caption: l.getAttribute('data-caption') || '' };
        }));
        showLightboxDesc(caption);
        lightbox.addEventListener('click', function(e) { if (e.target === lightbox) closeLightbox(); });
        if (!SG_STATE.lightboxListener) {
            document.addEventListener('keydown', handleKeydown);
            SG_STATE.lightboxListener = true;
        }
    }
    
    function closeLightbox() {
        var lightbox = document.getElementById('sg-custom-lightbox');
        if (lightbox) lightbox.remove();
        var desc = document.getElementById('sg-lightbox-desc');
        if (desc) desc.style.display = 'none';
    }
    
    function prevImg() {
        var lightbox = document.getElementById('sg-custom-lightbox');
        if (!lightbox) return;
        var links = JSON.parse(lightbox.dataset.links);
        var index = parseInt(lightbox.dataset.index);
        index = (index - 1 + links.length) % links.length;
        updateLightboxImage(links[index], index);
    }
    
    function nextImg() {
        var lightbox = document.getElementById('sg-custom-lightbox');
        if (!lightbox) return;
        var links = JSON.parse(lightbox.dataset.links);
        var index = parseInt(lightbox.dataset.index);
        index = (index + 1) % links.length;
        updateLightboxImage(links[index], index);
    }
    
    function updateLightboxImage(linkData, index) {
        var lightbox = document.getElementById('sg-custom-lightbox');
        if (!lightbox) return;
        document.getElementById('sg-lightbox-img').src = linkData.src;
        lightbox.dataset.index = index;
        showLightboxDesc(linkData.caption);
    }
    
    function showLightboxDesc(caption) {
        var desc = document.getElementById('sg-lightbox-desc');
        if (caption) { desc.innerText = caption; desc.style.display = 'block'; }
        else { desc.style.display = 'none'; }
    }
    
    function handleKeydown(event) {
        if (event.key === 'Escape') {
            closeLightbox();
            document.querySelectorAll('.sg-modal.sg-active').forEach(function(m) {
                var id = m.id.replace('sg-album-', '');
                closeModal(id, event);
            });
        } else if (document.getElementById('sg-custom-lightbox')) {
            if (event.key === 'ArrowLeft') prevImg();
            else if (event.key === 'ArrowRight') nextImg();
        }
    }
    
    window.sgOpenModal = openModal;
    window.sgCloseModal = closeModal;
    window.sgCloseLightbox = closeLightbox;
    window.sgPrevImg = prevImg;
    window.sgNextImg = nextImg;
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('sg-modal')) {
            var id = e.target.id.replace('sg-album-', '');
            closeModal(id, e);
        }
    });
})();
</script>
JSSCRIPT;
    }
}
