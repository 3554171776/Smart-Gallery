<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

include 'header.php';
include 'menu.php';

$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$options = Typecho_Widget::widget('Widget_Options');
$albums = $db->fetchAll($db->select()->from($prefix . 'smart_gallery_albums')->order('created', Typecho_Db::SORT_DESC));

?>
<style>
    /* 全局卡片样式 */
    .sg-card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
    .sg-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s; border: 1px solid #eaeaea; }
    .sg-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .sg-card-cover { width: 100%; height: 180px; object-fit: cover; background: #f5f5f5; }
    .sg-card-body { padding: 15px; }
    .sg-card-title { font-size: 16px; font-weight: bold; margin: 0 0 10px 0; }
    .sg-card-meta { font-size: 13px; color: #999; margin-bottom: 5px; }
    .sg-card-lock { background: #f0ad4e; color: #fff; font-size: 12px; padding: 2px 6px; border-radius: 3px; margin-left: 10px; }
    .sg-card-actions { display: flex; border-top: 1px solid #f0f0f0; }
    .sg-card-actions button { flex: 1; border: none; background: none; padding: 12px; cursor: pointer; font-size: 14px; color: #666; transition: background 0.2s; }
    .sg-card-actions button:hover { background: #f9f9f9; }
    .sg-card-actions button.primary { color: #467B96; font-weight: bold; }
    .sg-card-actions button.warn { color: #d9534f; }

    /* 修复：防止按钮溢出 */
    .sg-create-box { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 1px 5px rgba(0,0,0,0.05); flex-wrap: wrap; }
    .sg-create-box input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; flex: 1; min-width: 200px; }
    .sg-create-box .btn { flex-shrink: 0; white-space: nowrap; } /* 强制不换行不压缩 */

    /* 弹窗基础 */
    .sg-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; align-items: center; justify-content: center; }
    .sg-modal-content { background: #fff; border-radius: 8px; width: 90%; max-width: 1100px; max-height: 85vh; overflow-y: auto; }
    .sg-modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .sg-modal-header h3 { margin: 0; font-size: 18px; }
    .sg-modal-body { padding: 20px; }

    /* 图片管理样式 */
    .sg-img-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; }
    @media (max-width: 767px) { .sg-img-grid { grid-template-columns: repeat(2, 1fr); } }
    
    .sg-img-item { position: relative; border: 1px solid #eee; border-radius: 4px; overflow: hidden; background: #000; aspect-ratio: 1; cursor: pointer; }
    .sg-img-box-wrap img, .sg-img-box-wrap video { width: 100%; height: 100%; object-fit: contain; background: #f5f5f5; display: block; }
    
    .sg-checkbox-overlay {
        position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.1); 
        border: 3px solid transparent;
        transition: all 0.2s;
        display: flex; align-items: flex-start; justify-content: flex-end;
        padding: 5px;
        pointer-events: all; cursor: pointer;
    }
    .sg-checkbox-overlay:after {
        content: '';
        width: 22px; height: 22px;
        background: rgba(255,255,255,0.7);
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        transition: all 0.2s;
    }
    .sg-img-item.selected .sg-checkbox-overlay {
        background: rgba(70,123,150,0.25);
        border-color: #467B96;
    }
    .sg-img-item.selected .sg-checkbox-overlay:after {
        background: #467B96;
        border-color: #467B96;
        box-shadow: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z'/%3E%3C/svg%3E");
        background-size: 14px; background-position: center; background-repeat: no-repeat;
    }

    .sg-toolbar {
        display: flex; gap: 10px; margin-bottom: 15px; padding: 12px; background: #f0f2f5; border-radius: 6px; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 5; flex-wrap: wrap;
    }
    .sg-toolbar-left { display: flex; gap: 10px; align-items: center; flex: 1; }
    .sg-toolbar-right { display: flex; gap: 8px; }
    
    .sg-btn { padding: 6px 12px; border-radius: 4px; font-size: 13px; cursor: pointer; border: 1px solid #ddd; background: #fff; display: flex; align-items: center; gap: 4px; }
    .sg-btn:hover { background: #f9f9f9; }
    .sg-btn.primary { background: #467B96; color: #fff; border-color: #467B96; }
    .sg-btn.primary:hover { background: #3a6a7f; }
    .sg-btn.danger { background: #fff0f0; color: #d9534f; border-color: #ffccc7; }
    .sg-btn.danger:hover { background: #ffccc7; }
    
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
    .sg-form-group input, .sg-form-group textarea { width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
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

                <div class="sg-card-grid">
                    <?php foreach($albums as $album): ?>
                        <?php
                        $albumId = $album['id'];
                        $rawCover = $album['cover'];
                        $coverUrl = '';
                        if (!empty($rawCover)) {
                            if (preg_match('/^(https?:\/\/|\/\/)/i', $rawCover)) { $coverUrl = $rawCover; } 
                            else { $coverUrl = $options->siteUrl . 'usr/uploads/SmartGallery/' . $rawCover; }
                        } else {
                            $coverImg = $db->fetchRow($db->select('filename')->from($prefix . 'smart_gallery_images')->where('album_id = ?', $albumId)->order('RAND()')->limit(1));
                            if ($coverImg) { $coverUrl = $options->siteUrl . 'usr/uploads/SmartGallery/' . $coverImg['filename']; }
                        }
                        $count = $db->fetchObject($db->select('COUNT(id) as num')->from($prefix . 'smart_gallery_images')->where('album_id = ?', $albumId))->num;
                        ?>
                        <div class="sg-card">
                            <img src="<?php echo $coverUrl; ?>" class="sg-card-cover" alt="cover">
                            <div class="sg-card-body">
                                <div class="sg-card-title">
                                    <?php echo htmlspecialchars($album['name']); ?>
                                    <?php if(!empty($album['password'])): ?><span class="sg-card-lock">🔒 私密</span><?php endif; ?>
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
                <div class="sg-form-group"><label>简介</label><textarea name="description" id="edit-desc"></textarea></div>
                <div class="sg-form-group"><label>封面(留空自动)</label><input type="text" name="cover" id="edit-cover"></div>
                <div class="sg-form-group"><label>密码(留空公开)</label><input type="text" name="password" id="edit-pwd"></div>
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
            <div class="sg-toolbar">
                <div class="sg-toolbar-right" style="width:100%; justify-content: space-between;">
                    <span id="selected-count" class="sg-selected-info" style="display:none;">已选 0 张</span>
                    <div style="display:flex; gap:8px;">
                        <button class="sg-btn primary" onclick="handleSetCover()">设为封面</button>
                        <button class="sg-btn danger" onclick="handleDelete()">删除选中</button>
                    </div>
                </div>
            </div>
            
            <div id="upload-area" class="upload-area" onclick="document.getElementById('file-input').click()">
                <input type="file" id="file-input" multiple accept="image/*,video/*" style="display:none;" onchange="handleFileSelect(this.files)">
                <div style="font-size: 14px; color: #666;">
                    📁 点击选择图片或视频 (可多选)<br>
                    <span style="font-size: 12px; color: #999;">支持拖拽上传</span>
                </div>
            </div>
            
            <div id="file-list" class="file-list"></div>
            
            <div id="upload-controls" style="margin-top: 15px; display: none;">
                <button class="btn primary" style="width: 100%;" onclick="startUpload()" id="upload-btn">开始上传</button>
                <div class="upload-progress" id="upload-progress">
                    <div class="upload-progress-bar" id="progress-bar"></div>
                    <div class="upload-percent-text" id="progress-text">0%</div>
                </div>
            </div>
            
            <div style="margin: 20px 0; border-bottom: 1px solid #eee;"></div>
            <div id="images-list" class="sg-img-grid"></div>
        </div>
    </div>
</div>

<input type="hidden" id="current-album-id">

<script>
var selectedImgs = {};
var pendingFiles = [];

function createAlbum() {
    var name = document.getElementById('new-album-name').value;
    if(!name) { alert('请输入名称'); return; }
    var formData = new FormData();
    formData.append('name', name);
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=create-album', { method: 'POST', body: formData }).then(() => location.reload()).catch(err => alert('创建失败，请检查权限或路径'));
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
    document.getElementById('current-album-id').value = id;
    document.getElementById('images-modal').style.display = 'flex';
    loadImages(id);
}

function loadImages(id) {
    var box = document.getElementById('images-list');
    box.innerHTML = '<p style="grid-column: 1/-1; text-align:center;">加载中...</p>';
    clearSelection();
    
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=fetch-images&album_id=' + id)
        .then(res => res.json())
        .then(data => {
            var html = '';
            if(data.length === 0) {
                html = '<div style="grid-column: 1/-1; text-align:center; color:#999; padding: 40px 0;">暂无内容</div>';
            } else {
                data.forEach(img => {
                    var mediaUrl = '<?php echo $options->siteUrl; ?>usr/uploads/SmartGallery/' + img.filename;
                    var mediaHtml = (img.type === 'video') ? 
                        '<video src="' + mediaUrl + '" muted></video>' : 
                        '<img src="' + mediaUrl + '" loading="lazy">';

                    html += `
                    <div class="sg-img-item" data-id="${img.id}" data-filename="${img.filename}" data-album="${id}">
                        <div class="sg-img-box-wrap">
                            ${mediaHtml}
                        </div>
                        <div class="sg-checkbox-overlay" onclick="toggleSelect(this.parentElement, event)"></div>
                    </div>`;
                });
            }
            box.innerHTML = html;
        });
}

function handleFileSelect(files) {
    if (files.length === 0) return;
    for (var i = 0; i < files.length; i++) {
        pendingFiles.push(files[i]);
    }
    updateFileList();
}

function updateFileList() {
    var listEl = document.getElementById('file-list');
    var controlsEl = document.getElementById('upload-controls');
    if (pendingFiles.length === 0) {
        listEl.innerHTML = '';
        controlsEl.style.display = 'none';
        return;
    }
    controlsEl.style.display = 'block';
    var html = '';
    pendingFiles.forEach((file, index) => {
        html += `<div class="file-item"><span>${file.name} (${formatSize(file.size)})</span><button onclick="removeFile(${index})" style="background:none; border:none; color:#d9534f; cursor:pointer;">✕</button></div>`;
    });
    listEl.innerHTML = html;
}

function removeFile(index) {
    pendingFiles.splice(index, 1);
    updateFileList();
}

function formatSize(bytes) {
    if (bytes === 0) return '0 B';
    var k = 1024;
    var sizes = ['B', 'KB', 'MB', 'GB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function startUpload() {
    if (pendingFiles.length === 0) { alert('请先选择文件'); return; }
    var albumId = document.getElementById('current-album-id').value;
    var uploadBtn = document.getElementById('upload-btn');
    var progressEl = document.getElementById('upload-progress');
    var progressBar = document.getElementById('progress-bar');
    var progressText = document.getElementById('progress-text');
    
    uploadBtn.disabled = true;
    uploadBtn.innerText = '上传中...';
    progressEl.style.display = 'block';
    progressBar.style.width = '0%';
    progressText.innerText = '0%';
    
    var formData = new FormData();
    formData.append('album_id', albumId);
    pendingFiles.forEach((file, index) => {
        formData.append('files[]', file, file.name);
    });
    
    var xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            var percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            progressText.innerText = percent + '%';
        }
    });
    
    xhr.addEventListener('load', function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    pendingFiles = [];
                    updateFileList();
                    uploadBtn.disabled = false;
                    uploadBtn.innerText = '开始上传';
                    progressEl.style.display = 'none';
                    loadImages(albumId);
                } else {
                    alert('上传失败: ' + (response.msg || '未知错误'));
                    uploadBtn.disabled = false;
                    uploadBtn.innerText = '开始上传';
                }
            } catch(e) {
                alert('响应解析失败');
                uploadBtn.disabled = false;
                uploadBtn.innerText = '开始上传';
            }
        } else {
            alert('上传失败');
            uploadBtn.disabled = false;
            uploadBtn.innerText = '开始上传';
        }
    });
    xhr.addEventListener('error', function() { alert('网络错误'); uploadBtn.disabled = false; uploadBtn.innerText = '开始上传'; });
    xhr.open('POST', '<?php echo $options->index; ?>/action/smart-gallery?do=upload');
    xhr.send(formData);
}

var uploadArea = document.getElementById('upload-area');
uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', function(e) { e.preventDefault(); uploadArea.classList.remove('dragover'); });
uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    if (e.dataTransfer.files.length > 0) handleFileSelect(e.dataTransfer.files);
});

function toggleSelect(el, event) {
    var id = el.getAttribute('data-id');
    var filename = el.getAttribute('data-filename');
    var albumId = el.getAttribute('data-album');
    if (selectedImgs[id]) {
        delete selectedImgs[id];
        el.classList.remove('selected');
    } else {
        selectedImgs[id] = {filename: filename, albumId: albumId};
        el.classList.add('selected');
    }
    updateCount();
}

function updateCount() {
    var count = Object.keys(selectedImgs).length;
    var countEl = document.getElementById('selected-count');
    if(count > 0) {
        countEl.style.display = 'inline';
        countEl.innerText = '已选 ' + count + ' 张';
    } else {
        countEl.style.display = 'none'; // 修复：此处原本缺少单引号，导致JS解析错误
    }
}

function clearSelection() {
    selectedImgs = {};
    updateCount();
    document.querySelectorAll('.sg-img-item.selected').forEach(item => item.classList.remove('selected'));
}

function handleSetCover() {
    var ids = Object.keys(selectedImgs);
    if(ids.length === 0) { alert('请先勾选一张图片'); return; }
    if(ids.length > 1) { alert('封面设置仅支持一张图片，请勿多选'); return; }
    var imgData = selectedImgs[ids[0]];
    var formData = new FormData();
    formData.append('filename', imgData.filename);
    formData.append('album_id', imgData.albumId);
    fetch('<?php echo $options->index; ?>/action/smart-gallery?do=set-cover', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(d => { 
        if(d.status === 'success') { alert('封面已更新'); location.reload(); } 
        else alert('设置失败');
    });
}

function handleDelete() {
    var ids = Object.keys(selectedImgs);
    if(ids.length === 0) { alert('请先勾选图片'); return; }
    if(!confirm('确定要删除选中的 ' + ids.length + ' 张图片吗？此操作不可恢复。')) return;
    var promises = ids.map(id => fetch('<?php echo $options->index; ?>/action/smart-gallery?do=delete-img&id=' + id));
    Promise.all(promises).then(() => {
        var albumId = document.getElementById('current-album-id').value;
        loadImages(albumId);
    });
}

function deleteAlbum(id) {
    if(confirm('确定删除该相册？相册内图片将一并删除。')) {
        window.location.href = '<?php echo $options->index; ?>/action/smart-gallery?do=delete-album&id=' + id;
    }
}

function closeModal(mid) { document.getElementById(mid).style.display = 'none'; }

document.querySelectorAll('.sg-modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
});
</script>
<?php include 'copyright.php'; include 'common-js.php'; include 'footer.php'; ?>
