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
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/SmartGallery/';
        foreach ($images as $img) @unlink($uploadDir . $img['filename']);
        $this->db->query($this->db->delete($this->prefix . 'smart_gallery_images')->where('album_id = ?', $id));
        $this->db->query($this->db->delete($this->prefix . 'smart_gallery_albums')->where('id = ?', $id));
        $this->goBack();
    }

    public function upload()
    {
        if (session_status() == PHP_SESSION_NONE) session_start();
        $albumId = $this->request->get('album_id');
        if (empty($albumId)) die(json_encode(['status' => 'error', 'msg' => '未指定相册']));

        $files = $_FILES['files'];
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/SmartGallery/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $config = $this->getRealConfig();
        $useWebp = (isset($config['webp']) && $config['webp'] == '1');
        $imgQuality = isset($config['imgQuality']) ? intval($config['imgQuality']) : 80; 
        
        if ($imgQuality < 0) $imgQuality = 0;
        if ($imgQuality > 100) $imgQuality = 100;

        $count = 0;
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === 0) {
                $tmpName = $files['tmp_name'][$i];
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                $type = 'image';
                
                $timeStamp = time() . rand(100, 999);

                // 视频处理：直接保存，不再转码
                if (in_array($ext, ['mp4', 'webm', 'mov', 'avi', 'mkv'])) {
                    $type = 'video';
                    $filename = "video_{$timeStamp}." . $ext;
                    move_uploaded_file($tmpName, $uploadDir . $filename);
                } else {
                    // 图片处理：WebP转换或直接保存
                    if ($useWebp && function_exists('imagewebp')) {
                        $imageInfo = @getimagesize($tmpName);
                        $im = false;
                        
                        if ($imageInfo) {
                            switch ($imageInfo[2]) {
                                case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($tmpName); break;
                                case IMAGETYPE_PNG: 
                                    $im = @imagecreatefrompng($tmpName);
                                    if ($im) {
                                        $width = imagesx($im); $height = imagesy($im);
                                        $newIm = imagecreatetruecolor($width, $height);
                                        $white = imagecolorallocate($newIm, 255, 255, 255);
                                        imagefilledrectangle($newIm, 0, 0, $width, $height, $white);
                                        imagecopy($newIm, $im, 0, 0, 0, 0, $width, $height);
                                        imagedestroy($im); $im = $newIm;
                                    }
                                    break;
                                case IMAGETYPE_GIF: $im = @imagecreatefromgif($tmpName); break;
                            }
                        }
                        
                        if ($im) {
                            $filename = "q{$imgQuality}_{$timeStamp}.webp";
                            $filepath = $uploadDir . $filename;
                            @imagewebp($im, $filepath, $imgQuality);
                            @imagedestroy($im);
                            
                            if (!file_exists($filepath) || filesize($filepath) == 0) {
                                $filename = "raw_{$timeStamp}." . $ext;
                                move_uploaded_file($tmpName, $uploadDir . $filename);
                            }
                        } else {
                            $filename = "raw_{$timeStamp}." . $ext;
                            move_uploaded_file($tmpName, $uploadDir . $filename);
                        }
                    } else {
                        $filename = "raw_{$timeStamp}." . $ext;
                        move_uploaded_file($tmpName, $uploadDir . $filename);
                    }
                }

                $this->db->query($this->db->insert($this->prefix . 'smart_gallery_images')->rows(array(
                    'album_id' => $albumId, 'filename' => $filename,
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
            @unlink(__TYPECHO_ROOT_DIR__ . '/usr/uploads/SmartGallery/' . $image['filename']);
            $this->db->query($this->db->delete($this->prefix . 'smart_gallery_images')->where('id = ?', $id));
        }
        echo json_encode(['status' => 'success']);
    }

    public function checkPassword()
    {
        if (session_status() == PHP_SESSION_NONE) session_start();
        
        // 兼容性修复：直接从输入流获取JSON数据
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        // 兼容两种方式：JSON体 或 GET参数
        $albumId = isset($data['album_id']) ? $data['album_id'] : $this->request->get('album_id');
        $password = isset($data['password']) ? $data['password'] : $this->request->get('password');

        $album = $this->db->fetchRow($this->db->select('password')->from($this->prefix . 'smart_gallery_albums')->where('id = ?', $albumId));
        
        header('Content-Type: application/json'); // 确保返回JSON头
        
        if ($album && $album['password'] === $password) {
            $_SESSION['sg_unlocked_'.$albumId] = true;
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    }
}
