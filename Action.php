<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Typecho\Db;
use Typecho\Widget;
use Widget\Base\Options;
use Widget\ActionInterface;

class SmartGallery_Action extends Widget implements ActionInterface
{
    private $db;
    private $options;
    private $prefix;
    private $pdo;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->db = Db::get();
        $this->options = Options::alloc();
        $this->prefix = $this->db->getPrefix();
        
        $this->initPDO();
    }
    
    private function initPDO() {
        try {
            $adapter = $this->db->getAdapter();
            $reflection = new \ReflectionClass($adapter);
            
            if ($reflection->hasProperty('_connection')) {
                $property = $reflection->getProperty('_connection');
                $property->setAccessible(true);
                $connection = $property->getValue($adapter);
                
                if ($connection instanceof PDO) {
                    $this->pdo = $connection;
                    return;
                }
            }
        } catch (\Exception $e) {}
        
        try {
            $host = defined('TYPECHO_DB_HOST') ? TYPECHO_DB_HOST : 'localhost';
            $user = defined('TYPECHO_DB_USER') ? TYPECHO_DB_USER : '';
            $pass = defined('TYPECHO_DB_PASSWD') ? TYPECHO_DB_PASSWD : '';
            $name = defined('TYPECHO_DB_NAME') ? TYPECHO_DB_NAME : '';
            
            if ($user && $name) {
                $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
                $this->pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }
        } catch (\Exception $e) {}
    }

    public function action()
    {
        $this->on($this->request->is('do=create-album'))->createAlbum();
        $this->on($this->request->is('do=update-album'))->updateAlbum();
        $this->on($this->request->is('do=delete-album'))->deleteAlbum();
        $this->on($this->request->is('do=upload'))->upload();
        $this->on($this->request->is('do=delete-img'))->deleteImage();
        $this->on($this->request->is('do=set-cover'))->setCover();
        $this->on($this->request->is('do=fetch-images'))->fetchImages();
        $this->on($this->request->is('do=check-pwd'))->checkPassword();
        $this->on($this->request->is('do=scan-local'))->scanLocalImages();
        $this->on($this->request->is('do=insert-local'))->insertLocalImages();
    }

    private function goBack() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        header("Location: {$protocol}://{$host}/admin/extending.php?panel=SmartGallery/Panel.php");
        exit;
    }

    private function safeUnserialize($value) {
        if (empty($value) || !is_string($value)) return null;
        $data = @unserialize($value);
        if (is_array($data)) return $data;
        $data = @json_decode($value, true);
        if (is_array($data)) return $data;
        return null;
    }

    private function getRealConfig() {
        $defaultConfig = [
            'pcCols' => '4', 
            'mobileCols' => '2',
            'webp' => '0', 
            'imgQuality' => '80'
        ];
        
        $savedConfig = null;
        
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("SELECT value FROM {$this->prefix}options WHERE name = 'plugin:SmartGallery' LIMIT 1");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['value']) && !empty($row['value'])) {
                    $savedConfig = $this->safeUnserialize($row['value']);
                }
            } catch (\Exception $e) {}
        }
        
        if ($savedConfig === null) {
            try {
                $row = $this->db->fetchRow(
                    $this->db->select('value')
                    ->from('table.options')
                    ->where('name = ?', 'plugin:SmartGallery')
                );
                if ($row && isset($row['value'])) {
                    $savedConfig = $this->safeUnserialize($row['value']);
                }
            } catch (\Exception $e) {}
        }
        
        if ($savedConfig !== null && is_array($savedConfig)) {
            foreach ($savedConfig as $key => $value) {
                if ($value !== null && $value !== '') {
                    $defaultConfig[$key] = $value;
                }
            }
        }
        return $defaultConfig;
    }

    /**
     * 判断是否为本地图片（非相册上传）
     */
    private function isLocalImage($filename)
    {
        return (strpos($filename, 'SmartGallery/') !== 0);
    }

    public function createAlbum()
    {
        $name = $this->request->get('name');
        if (empty($name)) throw new \Typecho\Plugin\Exception('名称不能为空');
        $data = array(
            'name' => $name, 'description' => $this->request->get('description', ''),
            'cover' => $this->request->get('cover', ''), 'password' => $this->request->get('password', ''),
            'created' => time()
        );
        $this->db->query($this->db->insert($this->prefix . 'smart_gallery_albums')->rows($data));
        $this->goBack();
    }

    public function updateAlbum()
    {
        $id = $this->request->get('id');
        if (empty($id)) throw new \Typecho\Plugin\Exception('ID错误');
        $data = array(
            'name' => $this->request->get('name'), 'description' => $this->request->get('description', ''),
            'cover' => $this->request->get('cover', ''), 'password' => $this->request->get('password', '')
        );
        $this->db->query($this->db->update($this->prefix . 'smart_gallery_albums')->rows($data)->where('id = ?', $id));
        $this->goBack();
    }

    public function deleteAlbum()
    {
        $id = $this->request->get('id');
        if(empty($id)) throw new \Typecho\Plugin\Exception('ID无效');
        $images = $this->db->fetchAll($this->db->select('filename')->from($this->prefix . 'smart_gallery_images')->where('album_id = ?', $id));
        
        foreach ($images as $img) {
            // 只删除相册上传的文件，本地插入的不删除
            if (!$this->isLocalImage($img['filename'])) {
                $filePath = __TYPECHO_ROOT_DIR__ . '/usr/uploads/' . $img['filename'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }
        
        $this->db->query($this->db->delete($this->prefix . 'smart_gallery_images')->where('album_id = ?', $id));
        $this->db->query($this->db->delete($this->prefix . 'smart_gallery_albums')->where('id = ?', $id));
        $this->goBack();
    }

    /**
     * 获取PHP内存限制（字节）
     */
    private function getPhpMemoryLimitBytes()
    {
        $val = @ini_get('memory_limit');
        if ($val === false || $val === '' || $val === '-1') {
            return -1;
        }
        $last = strtolower(substr($val, -1));
        $num = (int)$val;
        switch ($last) {
            case 'g': return $num * 1024 * 1024 * 1024;
            case 'm': return $num * 1024 * 1024;
            case 'k': return $num * 1024;
            default: return (int)$val;
        }
    }

    /**
     * 确保有足够内存处理图片
     */
    private function ensureMemoryForImage($width, $height, $safetyFactor = 2.0)
    {
        $bytesPerPixel = 6.0;
        $estimated = (int)ceil($width * $height * $bytesPerPixel * $safetyFactor);

        $usage = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
        $limit = $this->getPhpMemoryLimitBytes();
        if ($limit > 0 && ($usage + $estimated) < $limit) {
            return true;
        }

        $targets = ['1024M', '768M', '512M'];
        foreach ($targets as $t) {
            @ini_set('memory_limit', $t);
            $limit = $this->getPhpMemoryLimitBytes();
            if ($limit > 0 && ($usage + $estimated) < $limit) {
                return true;
            }
        }
        return false;
    }

    /**
     * WebP压缩处理
     */
    private function processWebPCompression($filePath, $quality)
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            return ['success' => false, 'reason' => 'GD or WebP not supported'];
        }

        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return ['success' => false, 'reason' => 'Cannot get image info'];
        }

        $imgW = $imageInfo[0];
        $imgH = $imageInfo[1];
        $hasMemory = $this->ensureMemoryForImage($imgW, $imgH, 2.0);

        $sourceImage = null;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $sourceImage = @imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = @imagecreatefrompng($filePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = @imagecreatefromgif($filePath);
                break;
            case IMAGETYPE_WEBP:
                return ['success' => false, 'reason' => 'Already WebP format'];
            default:
                return ['success' => false, 'reason' => 'Unsupported image type'];
        }

        if (!$sourceImage) {
            return ['success' => false, 'reason' => 'Cannot create image resource'];
        }

        $srcW = imagesx($sourceImage);
        $srcH = imagesy($sourceImage);
        $maxSide = 1080;
        $longer = max($srcW, $srcH);
        $capScale = ($longer > $maxSide) ? ($maxSide / $longer) : 1.0;

        $memScale = 1.0;
        if (!$hasMemory) {
            $limit = $this->getPhpMemoryLimitBytes();
            $usage = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
            $available = ($limit > 0) ? max(0, $limit - $usage) : (512 * 1024 * 1024);
            $bytesPerPixel = 6.0 * 2.0;
            $maxPixels = (int)floor($available / $bytesPerPixel);
            if ($maxPixels > 0 && ($srcW * $srcH) > $maxPixels) {
                $memScale = sqrt($maxPixels / ($srcW * $srcH));
            }
        }

        $finalScale = max(0.01, min($capScale, $memScale));

        if ($finalScale < 0.999) {
            $dstW = max(1, (int)floor($srcW * $finalScale));
            $dstH = max(1, (int)floor($srcH * $finalScale));
            $tmp = imagecreatetruecolor($dstW, $dstH);
            if ($tmp) {
                if ($imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_GIF) {
                    imagealphablending($tmp, false);
                    imagesavealpha($tmp, true);
                    $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
                    imagefill($tmp, 0, 0, $transparent);
                }
                imagecopyresampled($tmp, $sourceImage, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
                imagedestroy($sourceImage);
                $sourceImage = $tmp;
            }
        }

        $outputPath = $filePath . '.webp';

        if ($imageInfo[2] === IMAGETYPE_PNG) {
            imagesavealpha($sourceImage, true);
        }

        $success = imagewebp($sourceImage, $outputPath, $quality);
        imagedestroy($sourceImage);

        if ($success && file_exists($outputPath)) {
            $originalSize = @filesize($filePath) ?: PHP_INT_MAX;
            $newSize = @filesize($outputPath) ?: PHP_INT_MAX;
            if ($newSize < $originalSize * 0.98) {
                return ['success' => true, 'path' => $outputPath];
            }
            @unlink($outputPath);
            return ['success' => false, 'reason' => 'WebP not smaller'];
        }

        return ['success' => false, 'reason' => 'Failed to save WebP'];
    }

    public function upload()
    {
        if (session_status() == PHP_SESSION_NONE) session_start();
        $albumId = $this->request->get('album_id');
        if (empty($albumId)) die(json_encode(['status' => 'error', 'msg' => '未指定相册']));

        $dateDir = date('Y/m');
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/SmartGallery/' . $dateDir . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $config = $this->getRealConfig();
        $useWebp = (isset($config['webp']) && $config['webp'] == '1');
        $imgQuality = isset($config['imgQuality']) ? intval($config['imgQuality']) : 80; 
        
        if ($imgQuality < 0) $imgQuality = 0;
        if ($imgQuality > 100) $imgQuality = 100;

        $count = 0;
        $files = $_FILES['files'];
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === 0) {
                $tmpName = $files['tmp_name'][$i];
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                $type = 'image';
                
                $timeStamp = time() . rand(100, 999);

                if (in_array($ext, ['mp4', 'webm', 'mov', 'avi', 'mkv'])) {
                    $type = 'video';
                    $filename = "video_{$timeStamp}." . $ext;
                    move_uploaded_file($tmpName, $uploadDir . $filename);
                } else {
                    $finalPath = $tmpName;
                    $finalExt = $ext;
                    
                    if ($useWebp) {
                        try {
                            $result = $this->processWebPCompression($tmpName, $imgQuality);
                            if ($result['success'] && isset($result['path']) && $result['path'] !== $tmpName) {
                                $finalPath = $result['path'];
                                $finalExt = 'webp';
                            }
                        } catch (\Exception $e) {}
                    }
                    
                    if ($finalExt === 'webp') {
                        $filename = "q{$imgQuality}_{$timeStamp}.webp";
                    } else {
                        $filename = "raw_{$timeStamp}." . $finalExt;
                    }
                    
                    if ($finalPath !== $tmpName) {
                        rename($finalPath, $uploadDir . $filename);
                    } else {
                        move_uploaded_file($tmpName, $uploadDir . $filename);
                    }
                }

                $dbFilename = 'SmartGallery/' . $dateDir . '/' . $filename;
                
                $this->db->query($this->db->insert($this->prefix . 'smart_gallery_images')->rows(array(
                    'album_id' => $albumId, 'filename' => $dbFilename,
                    'type'     => $type, 'created'  => time()
                )));
                $count++;
            }
        }
        
        echo json_encode(['status' => 'success', 'count' => $count]);
    }

    public function fetchImages()
    {
        $albumId = $this->request->get('album_id');
        $images = $this->db->fetchAll($this->db->select()->from($this->prefix . 'smart_gallery_images')->where('album_id = ?', $albumId)->order('order', Db::SORT_ASC));
        
        foreach ($images as &$img) {
            $img['isLocal'] = $this->isLocalImage($img['filename']);
        }
        
        echo json_encode($images);
    }

    public function setCover()
    {
        $albumId = $this->request->get('album_id');
        $filename = $this->request->get('filename');
        $this->db->query($this->db->update($this->prefix . 'smart_gallery_albums')->rows(['cover' => $filename])->where('id = ?', $albumId));
        echo json_encode(['status' => 'success']);
    }

    public function deleteImage()
    {
        $id = $this->request->get('id');
        $image = $this->db->fetchRow($this->db->select('filename')->from($this->prefix . 'smart_gallery_images')->where('id = ?', $id));
        if ($image) {
            $isLocal = $this->isLocalImage($image['filename']);
            
            // 只有非本地图片才删除文件
            if (!$isLocal) {
                $filePath = __TYPECHO_ROOT_DIR__ . '/usr/uploads/' . $image['filename'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            
            $this->db->query($this->db->delete($this->prefix . 'smart_gallery_images')->where('id = ?', $id));
        }
        echo json_encode(['status' => 'success']);
    }

    public function checkPassword()
    {
        if (session_status() == PHP_SESSION_NONE) session_start();
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $albumId = isset($data['album_id']) ? $data['album_id'] : $this->request->get('album_id');
        $password = isset($data['password']) ? $data['password'] : $this->request->get('password');

        $album = $this->db->fetchRow($this->db->select('password')->from($this->prefix . 'smart_gallery_albums')->where('id = ?', $albumId));
        
        header('Content-Type: application/json');
        
        if ($album && $album['password'] === $password) {
            $_SESSION['sg_unlocked_'.$albumId] = true;
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    }

    public function scanLocalImages()
    {
        $dir = $this->request->get('dir', '');
        $uploadBase = __TYPECHO_ROOT_DIR__ . '/usr/uploads/';
        
        $existingImages = $this->db->fetchAll($this->db->select('filename')->from($this->prefix . 'smart_gallery_images'));
        $existingFiles = array_column($existingImages, 'filename');
        
        $folders = [];
        $images = [];
        
        $currentPath = $uploadBase . $dir;
        if (!is_dir($currentPath)) {
            $currentPath = $uploadBase;
            $dir = '';
        }
        
        $items = @scandir($currentPath);
        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $fullPath = $currentPath . '/' . $item;
                $relPath = ($dir ? $dir . '/' : '') . $item;
                
                if (is_dir($fullPath)) {
                    $folders[] = [
                        'name' => $item,
                        'path' => $relPath,
                        'type' => 'folder'
                    ];
                } elseif (is_file($fullPath)) {
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    
                    $imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                    $videoExts = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
                    
                    if (in_array($ext, $imgExts)) {
                        $isInAlbum = in_array($relPath, $existingFiles);
                        $images[] = [
                            'name' => $item,
                            'path' => $relPath,
                            'type' => 'image',
                            'inAlbum' => $isInAlbum
                        ];
                    } elseif (in_array($ext, $videoExts)) {
                        $isInAlbum = in_array($relPath, $existingFiles);
                        $images[] = [
                            'name' => $item,
                            'path' => $relPath,
                            'type' => 'video',
                            'inAlbum' => $isInAlbum
                        ];
                    }
                }
            }
        }
        
        usort($folders, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        usort($images, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        
        echo json_encode([
            'status' => 'success',
            'currentDir' => $dir,
            'folders' => $folders,
            'images' => $images
        ]);
    }

    public function insertLocalImages()
    {
        if (session_status() == PHP_SESSION_NONE) session_start();
        
        $albumId = $this->request->get('album_id');
        
        $paths = $this->request->get('paths');
        if (empty($paths)) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            if (isset($data['paths'])) {
                $paths = $data['paths'];
            }
        }
        if (empty($paths)) {
            $paths = isset($_GET['paths']) ? $_GET['paths'] : (isset($_POST['paths']) ? $_POST['paths'] : []);
        }
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        
        if (empty($albumId)) {
            die(json_encode(['status' => 'error', 'msg' => '未指定相册']));
        }
        if (empty($paths)) {
            die(json_encode(['status' => 'error', 'msg' => '请选择图片']));
        }
        
        $count = 0;
        foreach ($paths as $path) {
            $fullPath = __TYPECHO_ROOT_DIR__ . '/usr/uploads/' . $path;
            if (!file_exists($fullPath)) continue;
            
            $exists = $this->db->fetchRow($this->db->select('id')->from($this->prefix . 'smart_gallery_images')->where('filename = ?', $path));
            if ($exists) continue;
            
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $videoExts = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
            $type = in_array($ext, $videoExts) ? 'video' : 'image';
            
            $this->db->query($this->db->insert($this->prefix . 'smart_gallery_images')->rows(array(
                'album_id' => $albumId, 
                'filename' => $path,
                'type'     => $type, 
                'created'  => time()
            )));
            $count++;
        }
        
        echo json_encode(['status' => 'success', 'count' => $count]);
    }
}
