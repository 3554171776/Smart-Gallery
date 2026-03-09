<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Smart Gallery 相册
 * 
 * @package Smart Gallery
 * @author 落花雨记
 * @version 1.0.0
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
              `created` int(10) unsigned DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;",
            "CREATE TABLE IF NOT EXISTS `{$prefix}smart_gallery_images` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `album_id` int(10) unsigned NOT NULL,
              `filename` varchar(255) NOT NULL,
              `description` varchar(255) DEFAULT '',
              `type` varchar(20) DEFAULT 'image',
              `order` int(10) unsigned DEFAULT 0,
              `created` int(10) unsigned DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `album_id` (`album_id`)
            ) DEFAULT CHARSET=utf8mb4;"
        );
        
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

        // 1. 显示设置
        $pcCols = new Typecho_Widget_Helper_Form_Element_Text('pcCols', NULL, '4', _t('PC端每行显示数量'));
        $form->addInput($pcCols);
        
        $mobileCols = new Typecho_Widget_Helper_Form_Element_Text('mobileCols', NULL, '2', _t('移动端每行显示数量'));
        $form->addInput($mobileCols);

        // 2. 图片压缩
        $webp = new Typecho_Widget_Helper_Form_Element_Radio('webp', 
            array('1' => _t('开启'), '0' => _t('关闭')), 
            '0', 
            _t('WebP 压缩'), 
            _t('开启后将自动把图片转换为 WebP 格式。<br><span style="color:#666;">当前状态：<strong style="color:#467B96;">' . $currentWebp . '</strong></span>'));
        $form->addInput($webp);

        $imgQuality = new Typecho_Widget_Helper_Form_Element_Text('imgQuality', NULL, '80', _t('图片压缩质量 (0-100)'), 
            _t('建议 80，数值越小文件越小但质量越低。<br><span style="color:#666;">当前设置：<strong style="color:#467B96;">' . $currentImgQuality . '</strong></span>'));
        $form->addInput($imgQuality);

        // 3. 状态概览
        $envHtml = '<div class="typecho-table-wrap" style="margin-top: 20px; background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #e9ecef;">
            <h4 style="margin-top:0; margin-bottom:10px; font-size:15px; color: #495057;">系统环境检测</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 13px; color: #495057;">
                <div><strong>GD 图形库：</strong>' . $gdStatus . '</div>
                <div><strong>WebP 函数：</strong>' . $webpFuncSupport . '</div>
            </div>
            <p style="font-size: 12px; color: #999; margin: 10px 0 0 0;">注：图片压缩功能依赖服务器 GD 图形库支持。</p>
        </div>';

        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html(_t($envHtml));
        $form->addItem($layout); 
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
        $defaultConfig = [
            'pcCols' => '4', 
            'mobileCols' => '2',
            'webp' => '0', 
            'imgQuality' => '80'
        ];
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

        // CSS样式
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

            .sg-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; overflow-y: auto; }
            .sg-modal.sg-active { display: flex; align-items: center; justify-content: center; }
            
            .sg-modal-content { max-width: 1200px; width: 90%; margin: 20px auto; padding: 20px; position: relative; background: #fff; border-radius: 8px; max-height: 90vh; overflow-y: auto; z-index: 10001; }
            
            .sg-modal-header { color: #333; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; position: sticky; top: 0; background: #fff; z-index: 10; }
            .sg-modal-header h2 { margin: 0; font-size: 22px; }
            
            .sg-close-btn { width: 30px; height: 30px; background: url(https://www.luohuayu.cn/usr/uploads/2026/03/guanbi.png) no-repeat center center; background-size: contain; cursor: pointer; transition: transform 0.2s; display: block; border: none; outline: none; background-color: transparent; position: relative; z-index: 11; }
            .sg-close-btn:hover { transform: rotate(90deg); }
            
            .sg-images-grid { display: grid; grid-gap: 20px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); font-size: 0; }
            @media (min-width: 768px) { .sg-images-grid { grid-template-columns: repeat(' . $pcCols . ', 1fr); } }
            @media (max-width: 767px) { .sg-images-grid { grid-template-columns: repeat(' . $mobileCols . ', 1fr); } }

            .sg-img-item { background: #fff; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.3s; position: relative; }
            .sg-img-item:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .sg-img-item .img-box { position: relative; aspect-ratio: 1; overflow: hidden; background: #f5f5f5; }
            .sg-img-item img, .sg-img-item video { width: 100%; height: 100%; object-fit: cover; display: block; }

            .sg-password-box { text-align: center; padding: 60px 20px; background: #fafafa; border-radius: 8px; }
            .sg-password-box h3 { margin-bottom: 20px; color: #333; font-size: 18px;}
            .sg-password-box .input-group { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
            .sg-password-box input { padding: 10px 15px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px; font-size: 16px; }
            .sg-password-box button { padding: 10px 25px; background: #467B96; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            .sg-password-box .msg { color: #e74c3c; margin-top: 15px; font-size: 14px; min-height: 20px; }
        </style>';

        $albumSelect = $db->select()->from($prefix . 'smart_gallery_albums');
        if ($targetAlbumId > 0) { $albumSelect->where('id = ?', $targetAlbumId); }
        $albums = $db->fetchAll($albumSelect->order('created', Typecho_Db::SORT_DESC));

        $html = $css . '<div class="sg-album-grid">';

        foreach ($albums as $album) {
            $albumId = $album['id'];
            $isPrivate = isset($album['password']) && !empty($album['password']);
            $description = isset($album['description']) ? $album['description'] : '';
            
            $coverUrl = '';
            if (isset($album['cover']) && !empty($album['cover'])) {
                $coverUrl = (preg_match('/^(https?:\/\/|\/\/)/i', $album['cover'])) ? $album['cover'] : $options->siteUrl . 'usr/uploads/SmartGallery/' . $album['cover'];
            } else {
                $coverImg = $db->fetchRow($db->select('filename')->from($prefix . 'smart_gallery_images')->where('album_id = ?', $albumId)->order('RAND()')->limit(1));
                if ($coverImg) { $coverUrl = $options->siteUrl . 'usr/uploads/SmartGallery/' . $coverImg['filename']; }
            }
            if (empty($coverUrl)) { $coverUrl = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 3"%3E%3Crect fill="%23eee" width="4" height="3"/%3E%3Ctext fill="%23ccc" x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" font-size="0.5"%3E空%3C/text%3E%3C/svg%3E'; }

            $html .= '
            <div class="sg-album-card" onclick="sgOpenModal('.$albumId.')">
                <div class="sg-album-cover-box">
                    <img src="' . $coverUrl . '" alt="' . htmlspecialchars($album['name']) . '">
                    ' . ($isPrivate ? '<div class="sg-lock-icon"></div>' : '') . '
                    <div class="sg-album-cover-title">' . htmlspecialchars($album['name']) . '</div>
                </div>
                <div class="sg-album-desc-box">
                    <div class="sg-album-desc">' . ($description ? htmlspecialchars($description) : '<span style="color:#999;font-style:italic;">暂无简介</span>') . '</div>
                </div>
            </div>';

            $html .= '
            <div id="sg-album-'.$albumId.'" class="sg-modal">
                <div class="sg-modal-content">
                    <div class="sg-modal-header">
                        <h2>' . htmlspecialchars($album['name']) . '</h2>
                        <button class="sg-close-btn" onclick="sgCloseModal(' . $albumId . ', event)"></button>
                    </div>
                    <div class="sg-body" id="sg-body-'.$albumId.'">';
            
            $unlocked = false;
            if ($isPrivate) {
                if (isset($_SESSION['sg_unlocked_'.$albumId]) && $_SESSION['sg_unlocked_'.$albumId] === true) {
                    $unlocked = true;
                }
            }

            if (!$isPrivate || $unlocked) {
                $html .= '<div class="sg-images-grid">';
                $images = $db->fetchAll($db->select()->from($prefix . 'smart_gallery_images')->where('album_id = ?', $albumId)->order('order', Typecho_Db::SORT_ASC));
                foreach ($images as $img) {
                    $imgUrl = $options->siteUrl . 'usr/uploads/SmartGallery/' . $img['filename'];
                    if (isset($img['type']) && $img['type'] === 'video') {
                        $html .= '<div class="sg-img-item"><div class="img-box"><video src="' . $imgUrl . '" controls style="object-fit:cover;"></video></div></div>';
                    } else {
                        $html .= '<div class="sg-img-item"><div class="img-box"><a href="' . $imgUrl . '" data-fancybox="gallery-' . $albumId . '"><img src="' . $imgUrl . '" loading="lazy"></a></div></div>';
                    }
                }
                $html .= '</div>';
            }
            $html .= '</div></div></div>';
        }

        $html .= '</div>';
        $html .= self::getJS($options);
        
        if ($return) return $html;
        echo $html;
    }

    private static function getJS($options) {
        return '<script>
            function sgOpenModal(id) {
                var modal = document.getElementById("sg-album-" + id);
                if (modal) {
                    modal.classList.add("sg-active");
                    var body = document.getElementById("sg-body-" + id);
                    if (!body.querySelector(".sg-images-grid") && !body.querySelector(".sg-password-box")) showPasswordBox(id);
                }
            }
            function showPasswordBox(id) {
                var body = document.getElementById("sg-body-" + id);
                body.innerHTML = `<div class="sg-password-box"><h3>该相册需要密码访问</h3><div class="input-group"><input type="password" id="sg_pwd_`+id+`" placeholder="请输入访问密码"><button onclick="checkGalleryPwd(`+id+`, event)">解锁</button></div><div class="msg" id="sg_msg_`+id+`"></div></div>`;
            }
            function sgCloseModal(id, e) {
                if(e && e.stopPropagation) { e.stopPropagation(); }
                if(e && e.preventDefault) { e.preventDefault(); }
                var modal = document.getElementById("sg-album-" + id);
                if (modal) { modal.classList.remove("sg-active"); }
            }
            document.addEventListener("click", function(e){ if(e.target.classList.contains("sg-modal")) e.target.classList.remove("sg-active"); });
            function checkGalleryPwd(id, evt) {
                if(evt && evt.preventDefault) evt.preventDefault();
                var pwdInput = document.getElementById("sg_pwd_" + id);
                var msgDiv = document.getElementById("sg_msg_" + id);
                var pwd = pwdInput.value;
                msgDiv.innerText = "验证中...";
                var formData = new FormData();
                formData.append("album_id", id);
                formData.append("password", pwd);
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "' . $options->index . '/action/smart-gallery?do=check-pwd", true);
                xhr.onload = function() {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if(data.status === "success") {
                            msgDiv.innerText = "解锁成功！";
                            var body = document.getElementById("sg-body-" + id);
                            body.innerHTML = "加载中...";
                            var imgXhr = new XMLHttpRequest();
                            imgXhr.open("GET", "' . $options->index . '/action/smart-gallery?do=fetch-images&album_id=" + id);
                            imgXhr.onload = function() {
                                var images = JSON.parse(imgXhr.responseText);
                                var html = \'<div class="sg-images-grid">\';
                                images.forEach(function(img) {
                                    var url = "' . $options->siteUrl . 'usr/uploads/SmartGallery/" + img.filename;
                                    if(img.type === "video") html += \'<div class="sg-img-item"><div class="img-box"><video src="\' + url + \'" controls></video></div></div>\';
                                    else html += \'<div class="sg-img-item"><div class="img-box"><a href="\' + url + \'" data-fancybox="gallery-\' + id + \'"><img src="\' + url + \'" loading="lazy"></a></div></div>\';
                                });
                                html += \'</div>\';
                                body.innerHTML = html;
                            };
                            imgXhr.send();
                        } else { msgDiv.innerText = "密码错误"; pwdInput.value = ""; }
                    } catch(e) { msgDiv.innerText = "请求失败"; }
                };
                xhr.send(formData);
            }
        </script>';
    }
}
