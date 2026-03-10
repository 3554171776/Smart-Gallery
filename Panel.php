<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

include 'header.php';
include 'menu.php';

$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$options = Typecho_Widget::widget('Widget_Options');
$albums = $db->fetchAll($db->select()->from($prefix . 'smart_gallery_albums')->order('sort_order', Typecho_Db::SORT_ASC)->order('created', Typecho_Db::SORT_DESC));

?>
<style>
    .sg-card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
    .sg-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s; border: 1px solid #eaeaea; cursor: grab; }
    .sg-card:active { cursor: grabbing; }
    .sg-card.dragging { opacity: 0.5; transform: scale(1.02); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .sg-card.drag-over { border: 2px dashed #467B96; background: #f0f7ff; }
    .sg-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .sg-card-cover { width: 100%; height: 180px; object-fit: cover; background: #f5f5f5; }
    .sg-card-body { padding: 15px; }
    .sg-card-title { font-size: 16px; font-weight: bold; margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .sg-card-meta { font-size: 13px; color: #999; margin-bottom: 5px; }
    .sg-card-lock { background: #f0ad4e; color: #fff; font-size: 12px; padding: 2px 6px; border-radius: 3px; }
    .sg-card-layout { background: #467B96; color: #fff; font-size: 11px; padding: 2px 6px; border-radius: 3px; }
    .sg-card-layout.masonry { background: #e65100; }
    .sg-card-actions { display: flex; border-top: 1px solid #f0f0f0; }
    .sg-card-actions button { flex: 1; border: none; background: none; padding: 12px; cursor: pointer; font-size: 14px; color: #666; transition: background 0.2s; }
    .sg-card-actions button:hover { background: #f9f9f9; }
    .sg-card-actions button.primary { color: #467B96; font-weight: bold; }
    .sg-card-actions button.warn { color: #d9534f; }

    .sg-create-box { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 1px 5px rgba(0,0,0,0.05); flex-wrap: wrap; }
    .sg-create-box input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; flex: 1; min-width: 200px; }
    .sg-create-box .btn { flex-shrink: 0; white-space: nowrap; }

    .sg-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; align-items: center; justify-content: center; }
    .sg-modal-content { background: #fff; border-radius: 8px; width: 90%; max-width: 1100px; max-height: 85vh; overflow-y: auto; }
    .sg-modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .sg-modal-header h3 { margin: 0; font-size: 18px; }
    .sg-modal-body { padding: 20px; }

    .sg-img-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; }
    @media (max-width: 767px) { .sg-img-grid { grid-template-columns: repeat(2, 1fr); } }
    
    .sg-preview-masonry { column-count: 6; column-gap: 15px; }
    @media (max-width: 767px) { .sg-preview-masonry { column-count: 2; } }
    
    .sg-img-item { position: relative; border: 1px solid #eee; border-radius: 4px; overflow: hidden; background: #f5f5f5; aspect-ratio: 1; cursor: grab; break-inside: avoid; margin-bottom: 0; }
    .sg-preview-masonry .sg-img-item { aspect-ratio: auto; margin-bottom: 15px; }
    .sg-img-item:active { cursor: grabbing; }
    .sg-img-item.dragging { opacity: 0.5; transform: scale(1.05); }
    .sg-img-item.drag-over { border: 2px dashed #467B96; }
    .sg-img-box-wrap img, .sg-img-box-wrap video { width: 100%; height: 100%; object-fit: contain; display: block; }
    .sg-preview-masonry .sg-img-box-wrap img, .sg-preview-masonry .sg-img-box-wrap video { height: auto; object-fit: cover; }
    
    .sg-video-badge { position: absolute; bottom: 8px; left: 8px; background: rgba(0,0,0,0.7); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; display: flex; align-items: center; gap: 4px; pointer-events: none; }
    .sg-local-badge { position: absolute; top: 8px; right: 8px; background: rgba(230, 81, 0, 0.9); color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; pointer-events: none; z-index: 2; }
    
    .sg-checkbox-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.1); border: 3px solid transparent; transition: all 0.2s; display: flex; align-items: flex-start; justify-content: flex-end; padding: 5px; pointer-events: all; cursor: pointer; z-index: 3; }
    .sg-checkbox-overlay:after { content: ''; width: 22px; height: 22px; background: rgba(255,255,255,0.7); border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: all 0.2s; }
    .sg-img-item.selected .sg-checkbox-overlay { background: rgba(70,123,150,0.25); border-color: #467B96; }
    .sg-img-item.selected .sg-checkbox-overlay:after { background: #467B96; border-color: #467B96; box-shadow: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z'/%3E%3C/svg%3E"); background-size: 14px; background-position: center; background-repeat: no-repeat; }

    .sg-toolbar { display: flex; gap: 10px; margin-bottom: 15px; padding: 12px; background: #f0f2f5; border-radius: 6px; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 5; flex-wrap: wrap; }
    .sg-toolbar-left { display: flex; gap: 10px; align-items: center; flex: 1; }
    .sg-toolbar-right { display: flex; gap: 8px; flex-wrap: wrap; }
    
    .sg-btn { padding: 6px 12px; border-radius: 4px; font-size: 13px; cursor: pointer; border: 1px solid #ddd; background: #fff; display: inline-flex; align-items: center; gap: 4px; }
    .sg-btn:hover { background: #f9f9f9; }
    .sg-btn.primary { background: #467B96; color: #fff; border-color: #467B96; }
    .sg-btn.primary:hover { background: #3a6a7f; }
    .sg-btn.danger { background: #fff0f0; color: #d9534f; border-color: #ffccc7; }
    .sg-btn.danger:hover { background: #ffccc7; }
    .sg-btn.local { background: #fff3e0; color: #e65100; border-color: #ffcc80; }
    .sg-btn.local:hover { background: #ffe0b2; }
    .sg-btn.move { background: #e8f5e9; color: #2e7d32; border-color: #c8e6c9; }
    .sg-btn.move:hover { background: #c8e6c9; }
    
    .sg-selected-info { font-size: 13px; color: #666; font-weight: bold; }
    
    .upload-area { border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 4px; background: #fff; cursor: pointer; transition: all 0.3s; }
    .upload-area:hover { border-color: #467B96; background: #f9fbfc; }
    .upload-area.dragover { border-color: #467B96; background: #f0f7ff; }
    
    .file-list { margin-top: 15px; text-align: left; max-height: 150px; overflow-y: auto; }
    .file-item { display: flex; justify-content: space-between; align-items: center; padding: 5px 10px; background: #f5f5f5; margin-bottom: 5px; border-radius: 3px; font-size: 12px; }
    
    .upload-progress { margin-top: 10px; display: none; }
    .upload-progress-bar { height: 4px; background: #467B96; width: 0; transition: width 0.3s; }
    .upload-percent-text { font-size: 12px; color: #467B96; margin-top: 5px; text-align: center; font-weight: bold; }

    .sg-form-group { margin-bottom: 15px; }
    .sg-form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .sg-form-group input, .sg-form-group textarea, .sg-form-group select { width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }

    .sg-move-list { max-height: 400px; overflow-y: auto; }
    .sg-move-item { padding: 12px 15px; border: 1px solid #eee; border-radius: 6px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 10px; }
    .sg-move-item:hover { background: #f0f7ff; border-color: #467B96; }
    .sg-move-item.selected { background: #e3f2fd; border-color: #467B96; }
    .sg-move-item-cover { width: 60px; height: 45px; object-fit: cover; border-radius: 4px; }

    .sg-desc-edit { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.8); padding: 8px; z-index: 10; display: none; }
    .sg-desc-edit input { width: 100%; padding: 5px 8px; border: none; border-radius: 3px; font-size: 12px; box-sizing: border-box; }
    .sg-img-item:hover .sg-desc-edit { display: block; }

    .local-browser { padding: 15px; background: #f9f9f9; border-radius: 6px; margin-bottom: 15px; }
    .local-browser-header { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; flex-wrap: wrap; }
    .local-browser-header .breadcrumb { flex: 1; font-size: 14px; color: #666; min-width: 150px; }
    .local-browser-header .breadcrumb span { cursor: pointer; color: #467B96; }
    
    .local-folders { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
    .local-folder { padding: 10px 15px; background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px; transition: all 0.2s; }
    .local-folder:hover { background: #f0f7ff; border-color: #467B96; }
    
    .local-images-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; max-height: 400px; overflow-y: auto; }
    @media (max-width: 767px) { .local-images-grid { grid-template-columns: repeat(3, 1fr); } }
    
    .local-img-item { position: relative; aspect-ratio: 1; background: #f5f5f5; border-radius: 4px; overflow: hidden; cursor: pointer; border: 3px solid transparent; }
    .local-img-item img { width: 100%; height: 100%; object-fit: cover; }
    .local-img-item.selected { border-color: #467B96; }
    .local-img-item .check-mark { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: #467B96; border-radius: 50%; display: none; align-items: center; justify-content: center; }
    .local-img-item.selected .check-mark { display: flex; }
    .local-img-item .in-album-badge { position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.6); color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 3px; }

    .sg-layout-toggle { display: flex; gap: 0; margin-bottom: 15px; border-radius: 6px; overflow: hidden; }
    .sg-layout-toggle button { flex: 1; padding: 10px 15px; border: 1px solid #ddd; background: #fff; cursor: pointer; transition: all 0.2s; font-weight: 500; color: #666; }
    .sg-layout-toggle button:first-child { border-radius: 6px 0 0 6px; }
    .sg-layout-toggle button:last-child { border-radius: 0 6px 6px 0; border-left: none; }
    .sg-layout-toggle button.active { background: #467B96; color: #fff; border-color: #467B96; }
</style>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12">
                <div class="sg-create-box">
                    <input type="text" id="new-album-name" placeholder="输入新相册名称...">
                    <button class="btn primary" onclick="createAlbum()">创建相册</button>
                </div>

                <div class="sg-card-grid" id="album-list">
                    <?php foreach($albums as $album): ?>
                        <?php
                        $albumId = $album['id'];
                        $rawCover = $album['cover'];
                        $coverUrl = '';
                        if (!empty($rawCover)) {
                            if (strpos($rawCover, 'http://') === 0 || strpos($rawCover, 'https://') === 0) { $coverUrl = $rawCover; } 
                            else { $coverUrl = $options->siteUrl . 'usr/uploads/' . $rawCover; }
                        } else {
                            $coverImg = $db->fetchRow($db->select('filename')->from($prefix . 'smart_gallery_images')->where('album_id = ?', $albumId)->order('sort_order', Typecho_Db::SORT_ASC)->limit(1));
                            if ($coverImg) { $coverUrl = $options->siteUrl . 'usr/uploads/' . $coverImg['filename']; }
                        }
                        $count = $db->fetchObject($db->select('COUNT(id) as num')->from($prefix . 'smart_gallery_images')->where('album_id = ?', $albumId))->num;
                        $layout = isset($album['layout']) ? $album['layout'] : 'grid';
                        ?>
                        <div class="sg-card" data-album-id="<?php echo $albumId; ?>" draggable="true">
                            <img src="<?php echo $coverUrl; ?>" class="sg-card-cover" alt="cover" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 4 3%22%3E%3Crect fill=%22%23eee%22 width=%224%22 height=%223%22/%3E%3Ctext fill=%22%23ccc%22 x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%220.5%22%3E无封面%3C/text%3E%3C/svg%3E'">
                            <div class="sg-card-body">
                                <div class="sg-card-title">
                                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($album['name']); ?></span>
                                    <?php if(!empty($album['password'])): ?><span class="sg-card-lock">私密</span><?php endif; ?>
                                    <span class="sg-card-layout <?php echo $layout === 'masonry' ? 'masonry' : ''; ?>" id="layout-badge-<?php echo $albumId; ?>"><?php echo $layout === 'masonry' ? '瀑布' : '常规'; ?></span>
                                </div>
                                <div class="sg-card-meta">简介: <?php echo htmlspecialchars($album['description'] ?: '暂无'); ?></div>
                                <div class="sg-card-meta">数量: <?php echo $count; ?> 张</div>
                            </div>
                            <div class="sg-card-actions">
                                <button class="primary" onclick='openEditModal(<?php echo json_encode($album); ?>)'>编辑</button>
                                <button onclick="openImagesModal(<?php echo $albumId; ?>)">管理图片</button>
                                <button class="warn" onclick="deleteAlbum(<?php echo $albumId; ?>)">删除</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 编辑弹窗 -->
<div id="edit-modal" class="sg-modal-overlay">
    <div class="sg-modal-content">
        <div class="sg-modal-header">
            <h3>编辑相册</h3>
            <button onclick="closeModal('edit-modal')">&times;</button>
        </div>
        <div class="sg-modal-body">
            <form action="<?php echo $options->index; ?>/action/smart-gallery?do=update-album" method="post">
                <input type="hidden" name="id" id="edit-id">
                <div class="sg-form-group"><label>名称</label><input type="text" name="name" id="edit-name" required></div>
                <div class="sg-form-group"><label>简介</label><textarea name="description" id="edit-desc" rows="3"></textarea></div>
                <div class="sg-form-group"><label>封面(留空自动)</label><input type="text" name="cover" id="edit-cover" placeholder="可输入图片URL"></div>
                <div class="sg-form-group"><label>密码(留空公开)</label><input type="text" name="password" id="edit-pwd" placeholder="留空则公开访问"></div>
                <button type="submit" class="btn primary" style="width:100%;">保存</button>
            </form>
        </div>
    </div>
</div>

<!-- 图片管理弹窗 -->
<div id="images-modal" class="sg-modal-overlay">
    <div class="sg-modal-content">
        <div class="sg-modal-header">
            <h3>管理图片</h3>
            <button onclick="closeModal('images-modal')">&times;</button>
        </div>
        <div class="sg-modal-body">
            <div class="sg-layout-toggle">
                <button id="layout-grid-btn" onclick="setLayout('grid')">常规网格</button>
                <button id="layout-masonry-btn" onclick="setLayout('masonry')">瀑布流</button>
            </div>
            
            <div class="sg-toolbar">
                <div class="sg-toolbar-left">
                    <span id="selected-count" class="sg-selected-info" style="display:none;">已选 0 张</span>
                </div>
                <div class="sg-toolbar-right">
                    <button class="sg-btn" onclick="toggleSelectAll()" id="btn-select-all">全选</button>
                    <button class="sg-btn local" onclick="openLocalBrowser()">本地图片</button>
                    <button class="sg-btn move" onclick="openMoveModal()">移动</button>
                    <button class="sg-btn primary" onclick="handleSetCover()">封面</button>
                    <button class="sg-btn danger" onclick="handleDelete()">删除</button>
                </div>
            </div>
            
            <div id="local-browser" class="local-browser" style="display:none;">
                <div class="local-browser-header">
                    <div class="breadcrumb">路径: <span onclick="loadLocalDir('')">uploads</span><span id="breadcrumb-path"></span></div>
                    <button class="sg-btn primary" onclick="insertSelectedLocal()">插入选中</button>
                    <button class="sg-btn" onclick="closeLocalBrowser()">关闭</button>
                </div>
                <div id="local-folders" class="local-folders"></div>
                <div id="local-images" class="local-images-grid"></div>
            </div>
            
            <div id="upload-area" class="upload-area" onclick="document.getElementById('file-input').click()">
                <input type="file" id="file-input" multiple accept="image/*,video/*" style="display:none;" onchange="handleFileSelect(this.files)">
                <div style="font-size: 14px; color: #666;">点击选择图片或视频 (可多选)<br><span style="font-size: 12px; color: #999;">支持拖拽上传 | 拖拽图片可排序</span></div>
            </div>
            
            <div id="file-list" class="file-list"></div>
            
            <div id="upload-controls" style="margin-top: 15px; display: none;">
                <button class="btn primary" style="width: 100%;" onclick="startUpload()" id="upload-btn">开始上传</button>
                <div class="upload-progress" id="upload-progress"><div class="upload-progress-bar" id="progress-bar"></div><div class="upload-percent-text" id="progress-text">0%</div></div>
            </div>
            
            <div style="margin: 15px 0; border-bottom: 1px solid #eee;"></div>
            <div id="images-list" class="sg-img-grid"></div>
        </div>
    </div>
</div>

<!-- 移动图片弹窗 -->
<div id="move-modal" class="sg-modal-overlay">
    <div class="sg-modal-content" style="max-width: 500px;">
        <div class="sg-modal-header"><h3>移动到其他相册</h3><button onclick="closeModal('move-modal')">&times;</button></div>
        <div class="sg-modal-body">
            <div class="sg-move-list" id="move-album-list"></div>
            <button class="btn primary" style="width:100%; margin-top:15px;" onclick="executeMove()">确认移动</button>
        </div>
    </div>
</div>

<input type="hidden" id="current-album-id">
<input type="hidden" id="current-album-layout" value="grid">

<script>
var selectedImgs = {};
var pendingFiles = [];
var localSelectedImgs = [];
var currentLocalDir = '';
var allImages = [];
var targetMoveAlbum = null;
var currentAlbumLayout = 'grid';
var currentAlbumId = 0;
var draggedItem = null;

// ========== 修复后的相册拖拽排序 ==========
function initAlbumDragSort() {
    var container = document.getElementById('album-list');
    var cards = container.querySelectorAll('.sg-card');

    cards.forEach(function(card) {
        card.addEventListener('dragstart', function(e) {
            draggedItem = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        card.addEventListener('dragend', function(e) {
            card.classList.remove('dragging');
            draggedItem = null;
            // 拖拽结束后保存顺序
            saveAlbumOrder();
        });

        card.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            if (draggedItem && draggedItem !== card) {
                var container = document.getElementById('album-list');
                var afterElement = getDragAfterElement(container, e.clientY);
                
                if (afterElement == null) {
                    container.appendChild(draggedItem);
                } else {
                    container.insertBefore(draggedItem, afterElement);
                }
            }
        });

        card.addEventListener('drop', function(e) {
            e.preventDefault();
        });
    });
}

// 辅助函数：判断拖拽位置
function getDragAfterElement(container, y) {
    var draggableElements = [...container.querySelectorAll('.sg-card:not(.dragging)')];

    return draggableElements.reduce(function(closest, child) {
        var box = child.getBoundingClientRect();
        var offset = y - box.top - box.height / 2;
        
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function saveAlbumOrder() {
    var cards = document.querySelectorAll('#album-list .sg-card');
    var orders = [];
    cards.forEach(function(card, index) {
        orders.push({ id: parseInt(card.getAttribute('data-album-id')), order: index });
    });
    
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=save-album-order', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orders: orders })
    });
}

function createAlbum() {
    var name = document.getElementById('new-album-name').value;
    if(!name) { alert('请输入名称'); return; }
    var formData = new FormData(); formData.append('name', name);
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=create-album', { method: 'POST', body: formData }).then(() => location.reload());
}

function openEditModal(album) {
    document.getElementById('edit-id').value = album.id;
    document.getElementById('edit-name').value = album.name;
    document.getElementById('edit-desc').value = album.description || '';
    document.getElementById('edit-cover').value = album.cover || '';
    document.getElementById('edit-pwd').value = album.password || '';
    document.getElementById('edit-modal').style.display = 'flex';
}

function openImagesModal(id) {
    currentAlbumId = id;
    document.getElementById('current-album-id').value = id;
    document.getElementById('images-modal').style.display = 'flex';
    loadImages(id);
}

function setLayout(layout) {
    currentAlbumLayout = layout;
    document.getElementById('layout-grid-btn').classList.toggle('active', layout === 'grid');
    document.getElementById('layout-masonry-btn').classList.toggle('active', layout === 'masonry');
    updatePreviewLayout(layout);
    
    var layoutBadge = document.getElementById('layout-badge-' + currentAlbumId);
    if (layoutBadge) {
        layoutBadge.textContent = layout === 'masonry' ? '瀑布' : '常规';
        layoutBadge.classList.toggle('masonry', layout === 'masonry');
    }
    
    var formData = new FormData();
    formData.append('id', currentAlbumId);
    formData.append('layout', layout);
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=update-album-layout', { method: 'POST', body: formData });
}

function updatePreviewLayout(layout) {
    var box = document.getElementById('images-list');
    if (layout === 'masonry') {
        box.className = 'sg-preview-masonry';
        box.style.display = 'block';
    } else {
        box.className = 'sg-img-grid';
        box.style.display = 'grid';
    }
}

function loadImages(id) {
    var box = document.getElementById('images-list');
    box.innerHTML = '<p style="text-align:center;padding:40px;color:#999;">加载中...</p>';
    clearSelection();
    
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=get-album-info&album_id=' + id)
        .then(res => res.json())
        .then(albumData => {
            currentAlbumLayout = albumData.layout || 'grid';
            document.getElementById('layout-grid-btn').classList.toggle('active', currentAlbumLayout === 'grid');
            document.getElementById('layout-masonry-btn').classList.toggle('active', currentAlbumLayout === 'masonry');
            return fetch('<?php echo $options->index; ?>/action/smart-gallery?do=fetch-images&album_id=' + id);
        })
        .then(res => res.json())
        .then(data => {
            allImages = data;
            if(data.length === 0) {
                box.innerHTML = '<div style="text-align:center;color:#999;padding:40px;">暂无内容</div>';
            } else {
                var html = '';
                data.forEach(function(img) {
                    var mediaUrl = '<?php echo $options->siteUrl; ?>usr/uploads/' + img.filename;
                    var isVideo = (img.type === 'video');
                    var isLocal = img.isLocal;
                    var desc = img.description || '';
                    
                    var mediaHtml = isVideo ? '<video src="' + mediaUrl + '" muted></video>' : '<img src="' + mediaUrl + '">';
                    var videoBadge = isVideo ? '<div class="sg-video-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>视频</div>' : '';
                    var localBadge = isLocal ? '<div class="sg-local-badge">本地</div>' : '';

                    html += '<div class="sg-img-item" data-id="' + img.id + '" data-filename="' + img.filename + '" data-album="' + id + '" data-local="' + (isLocal ? '1' : '0') + '" draggable="true">';
                    html += '<div class="sg-img-box-wrap">' + mediaHtml + '</div>' + videoBadge + localBadge;
                    html += '<div class="sg-checkbox-overlay" onclick="toggleSelect(this.parentElement, event)"></div>';
                    html += '<div class="sg-desc-edit" onclick="event.stopPropagation()"><input type="text" value="' + desc.replace(/"/g, '&quot;') + '" placeholder="添加描述..." onchange="saveImageDesc(' + img.id + ', this.value)"></div>';
                    html += '</div>';
                });
                box.innerHTML = html;
                updatePreviewLayout(currentAlbumLayout);
                initImageDragSort();
            }
        });
}

function initImageDragSort() {
    var container = document.getElementById('images-list');
    var items = container.querySelectorAll('.sg-img-item');
    var draggedImg = null;
    
    items.forEach(function(item) {
        item.addEventListener('dragstart', function(e) {
            draggedImg = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        
        item.addEventListener('dragend', function(e) {
            item.classList.remove('dragging');
            draggedImg = null;
            saveImageOrder();
        });
        
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (draggedImg && draggedImg !== item) {
                var container = document.getElementById('images-list');
                var afterElement = getDragAfterElement(container, e.clientY);
                if (afterElement == null) {
                    container.appendChild(draggedImg);
                } else {
                    container.insertBefore(draggedImg, afterElement);
                }
            }
        });
    });
}

function saveImageOrder() {
    var items = document.querySelectorAll('#images-list .sg-img-item');
    var orders = [];
    items.forEach(function(item, index) {
        orders.push({ id: parseInt(item.getAttribute('data-id')), order: index });
    });
    
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=save-img-order', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orders: orders })
    });
}

function saveImageDesc(id, desc) {
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=save-img-desc', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, description: desc })
    });
}

function toggleSelectAll() {
    var items = document.querySelectorAll('#images-list .sg-img-item');
    var allSelected = Object.keys(selectedImgs).length === items.length && items.length > 0;
    
    if (allSelected) { clearSelection(); } 
    else {
        items.forEach(function(item) {
            var id = item.getAttribute('data-id');
            var filename = item.getAttribute('data-filename');
            var albumId = item.getAttribute('data-album');
            selectedImgs[id] = {filename: filename, albumId: albumId};
            item.classList.add('selected');
        });
    }
    updateCount();
}

function openLocalBrowser() { document.getElementById('local-browser').style.display = 'block'; localSelectedImgs = []; loadLocalDir(''); }
function closeLocalBrowser() { document.getElementById('local-browser').style.display = 'none'; localSelectedImgs = []; }

function loadLocalDir(dir) {
    currentLocalDir = dir;
    var foldersEl = document.getElementById('local-folders');
    var imagesEl = document.getElementById('local-images');
    var breadcrumbEl = document.getElementById('breadcrumb-path');
    
    foldersEl.innerHTML = '<p>加载中...</p>'; imagesEl.innerHTML = '';
    
    if(dir) {
        var parts = dir.split('/'); var path = ''; var html = '';
        parts.forEach(function(part, i) { path += (i > 0 ? '/' : '') + part; html += ' / <span onclick="loadLocalDir(\'' + path + '\')">' + part + '</span>'; });
        breadcrumbEl.innerHTML = html;
    } else { breadcrumbEl.innerHTML = ''; }
    
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=scan-local&dir=' + encodeURIComponent(dir))
        .then(res => res.json())
        .then(data => {
            var folderHtml = '';
            if(data.folders && data.folders.length > 0) { data.folders.forEach(function(folder) { folderHtml += '<div class="local-folder" onclick="loadLocalDir(\'' + folder.path + '\')">📁 ' + folder.name + '</div>'; }); }
            foldersEl.innerHTML = folderHtml || '<p style="color:#999;font-size:13px;">无子文件夹</p>';
            
            var imgHtml = '';
            if(data.images && data.images.length > 0) {
                data.images.forEach(function(img) {
                    var imgUrl = '<?php echo $options->siteUrl; ?>usr/uploads/' + img.path;
                    var inAlbumBadge = img.inAlbum ? '<div class="in-album-badge">已在相册</div>' : '';
                    imgHtml += '<div class="local-img-item" data-path="' + img.path + '" onclick="toggleLocalSelect(this)"><img src="' + imgUrl + '"><div class="check-mark"><svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg></div>' + inAlbumBadge + '</div>';
                });
            }
            imagesEl.innerHTML = imgHtml || '<p style="color:#999;font-size:13px;grid-column:1/-1;text-align:center;">无图片</p>';
        });
}

function toggleLocalSelect(el) {
    var path = el.getAttribute('data-path');
    var idx = localSelectedImgs.indexOf(path);
    if (idx > -1) { localSelectedImgs.splice(idx, 1); el.classList.remove('selected'); } 
    else { localSelectedImgs.push(path); el.classList.add('selected'); }
}

function insertSelectedLocal() {
    if(localSelectedImgs.length === 0) { alert('请先选择图片'); return; }
    var albumId = document.getElementById('current-album-id').value;
    var params = 'album_id=' + encodeURIComponent(albumId);
    localSelectedImgs.forEach(function(path) { params += '&paths[]=' + encodeURIComponent(path); });
    
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=insert-local', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') { alert('成功插入 ' + data.count + ' 张图片'); closeLocalBrowser(); loadImages(albumId); } 
        else { alert('插入失败: ' + (data.msg || '未知错误')); }
    });
}

function handleFileSelect(files) { if (files.length === 0) return; for (var i = 0; i < files.length; i++) { pendingFiles.push(files[i]); } updateFileList(); }
function updateFileList() {
    var listEl = document.getElementById('file-list'); var controlsEl = document.getElementById('upload-controls');
    if (pendingFiles.length === 0) { listEl.innerHTML = ''; controlsEl.style.display = 'none'; return; }
    controlsEl.style.display = 'block'; var html = '';
    pendingFiles.forEach(function(file, index) { html += '<div class="file-item"><span>' + file.name + ' (' + formatSize(file.size) + ')</span><button onclick="removeFile(' + index + ')" style="background:none; border:none; color:#d9534f; cursor:pointer;">✕</button></div>'; });
    listEl.innerHTML = html;
}
function removeFile(index) { pendingFiles.splice(index, 1); updateFileList(); }
function formatSize(bytes) { if (bytes === 0) return '0 B'; var k = 1024; var sizes = ['B', 'KB', 'MB', 'GB']; var i = Math.floor(Math.log(bytes) / Math.log(k)); return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]; }

function startUpload() {
    if (pendingFiles.length === 0) { alert('请先选择文件'); return; }
    var albumId = document.getElementById('current-album-id').value;
    var uploadBtn = document.getElementById('upload-btn');
    var progressEl = document.getElementById('upload-progress');
    var progressBar = document.getElementById('progress-bar');
    var progressText = document.getElementById('progress-text');
    
    uploadBtn.disabled = true; uploadBtn.innerText = '上传中...';
    progressEl.style.display = 'block'; progressBar.style.width = '0%'; progressText.innerText = '0%';
    
    var formData = new FormData(); formData.append('album_id', albumId);
    pendingFiles.forEach(function(file) { formData.append('files[]', file, file.name); });
    
    var xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', function(e) { if (e.lengthComputable) { var percent = Math.round((e.loaded / e.total) * 100); progressBar.style.width = percent + '%'; progressText.innerText = percent + '%'; } });
    xhr.addEventListener('load', function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.status === 'success') { pendingFiles = []; updateFileList(); uploadBtn.disabled = false; uploadBtn.innerText = '开始上传'; progressEl.style.display = 'none'; loadImages(albumId); } 
                else { alert('上传失败: ' + (response.msg || '未知错误')); uploadBtn.disabled = false; uploadBtn.innerText = '开始上传'; }
            } catch(e) { alert('响应解析失败'); uploadBtn.disabled = false; uploadBtn.innerText = '开始上传'; }
        }
    });
    xhr.open('POST', '<?php echo $options->index; ?>/action/smart-gallery?do=upload'); xhr.send(formData);
}

var uploadArea = document.getElementById('upload-area');
uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', function(e) { e.preventDefault(); uploadArea.classList.remove('dragover'); });
uploadArea.addEventListener('drop', function(e) { e.preventDefault(); uploadArea.classList.remove('dragover'); if (e.dataTransfer.files.length > 0) handleFileSelect(e.dataTransfer.files); });

function toggleSelect(el, event) {
    var id = el.getAttribute('data-id'); var filename = el.getAttribute('data-filename'); var albumId = el.getAttribute('data-album');
    if (selectedImgs[id]) { delete selectedImgs[id]; el.classList.remove('selected'); } 
    else { selectedImgs[id] = {filename: filename, albumId: albumId}; el.classList.add('selected'); }
    updateCount();
}

function updateCount() {
    var count = Object.keys(selectedImgs).length; var countEl = document.getElementById('selected-count');
    if(count > 0) { countEl.style.display = 'inline'; countEl.innerText = '已选 ' + count + ' 张'; } 
    else { countEl.style.display = 'none'; }
}

function clearSelection() { selectedImgs = {}; updateCount(); document.querySelectorAll('.sg-img-item.selected').forEach(function(item) { item.classList.remove('selected'); }); }

function handleSetCover() {
    var ids = Object.keys(selectedImgs);
    if(ids.length === 0) { alert('请先勾选一张图片'); return; }
    if(ids.length > 1) { alert('封面设置仅支持一张图片，请勿多选'); return; }
    var imgData = selectedImgs[ids[0]];
    var formData = new FormData(); formData.append('filename', imgData.filename); formData.append('album_id', imgData.albumId);
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=set-cover', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(function(d) { if(d.status === 'success') { alert('封面已更新'); location.reload(); } else alert('设置失败'); });
}

function handleDelete() {
    var ids = Object.keys(selectedImgs);
    if(ids.length === 0) { alert('请先勾选图片'); return; }
    if(!confirm('确定要移除选中的 ' + ids.length + ' 张图片吗？\n\n注意：本地图片只会从相册移除，不会删除源文件。')) return;
    var promises = ids.map(function(id) { return fetch('<?php echo $options->index; ?>/action/smart-gallery?do=delete-img&id=' + id); });
    Promise.all(promises).then(function() { var albumId = document.getElementById('current-album-id').value; loadImages(albumId); });
}

function openMoveModal() {
    var ids = Object.keys(selectedImgs);
    if(ids.length === 0) { alert('请先选择要移动的图片'); return; }
    var currentAlbumId = document.getElementById('current-album-id').value; var listEl = document.getElementById('move-album-list');
    var albums = <?php echo json_encode($albums); ?>; var html = '';
    albums.forEach(function(album) {
        if(album.id != currentAlbumId) {
            var coverUrl = '';
            if(album.cover) { coverUrl = album.cover.indexOf('http') === 0 ? album.cover : '<?php echo $options->siteUrl; ?>usr/uploads/' + album.cover; }
            html += '<div class="sg-move-item" data-album-id="' + album.id + '" onclick="selectMoveTarget(this)">';
            if(coverUrl) { html += '<img src="' + coverUrl + '" class="sg-move-item-cover" onerror="this.style.display=\'none\'">'; }
            html += '<span>' + album.name + '</span></div>';
        }
    });
    if(!html) { html = '<p style="color:#999; text-align:center;">没有其他相册可移动</p>'; }
    listEl.innerHTML = html; targetMoveAlbum = null;
    document.getElementById('move-modal').style.display = 'flex';
}

function selectMoveTarget(el) { document.querySelectorAll('.sg-move-item').forEach(function(item) { item.classList.remove('selected'); }); el.classList.add('selected'); targetMoveAlbum = el.getAttribute('data-album-id'); }

function executeMove() {
    if(!targetMoveAlbum) { alert('请选择目标相册'); return; }
    var ids = Object.keys(selectedImgs);
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=move-images', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids: ids, target_album: parseInt(targetMoveAlbum) }) })
    .then(res => res.json())
    .then(function(data) {
        if(data.status === 'success') { alert('成功移动 ' + data.count + ' 张图片'); closeModal('move-modal'); clearSelection(); var albumId = document.getElementById('current-album-id').value; loadImages(albumId); } 
        else { alert('移动失败: ' + (data.msg || '未知错误')); }
    });
}

function deleteAlbum(id) { if(confirm('确定删除该相册？相册内图片将一并删除。')) { window.location.href = '<?php echo $options->index; ?>/action/smart-gallery?do=delete-album&id=' + id; } }

function closeModal(mid) { document.getElementById(mid).style.display = 'none'; }

document.querySelectorAll('.sg-modal-overlay').forEach(function(modal) { modal.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); }); });

document.addEventListener('DOMContentLoaded', function() { initAlbumDragSort(); });
</script>
<?php include 'copyright.php'; include 'common-js.php'; include 'footer.php'; ?>
