<?php
// 检查是否是文件上传请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['music'])) {
    $uploadDir = 'music/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = basename($_FILES['music']['name']);
    $targetPath = $uploadDir . $fileName;
    
    // 检查文件是否为MP3格式
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
    if ($fileType === 'mp3' && $_FILES['music']['error'] === UPLOAD_ERR_OK) {
        if (move_uploaded_file($_FILES['music']['tmp_name'], $targetPath)) {
            echo json_encode(['success' => true, 'message' => '文件上传成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '文件上传失败：无法移动文件']);
        }
    } else {
        $errorMsg = '请上传有效的MP3文件';
        if ($_FILES['music']['error'] !== UPLOAD_ERR_OK) {
            switch ($_FILES['music']['error']) {
                case UPLOAD_ERR_INI_SIZE: $errorMsg = '文件大小超过服务器限制'; break;
                case UPLOAD_ERR_FORM_SIZE: $errorMsg = '文件大小超过表单限制'; break;
                case UPLOAD_ERR_PARTIAL: $errorMsg = '文件上传不完整'; break;
                case UPLOAD_ERR_NO_FILE: $errorMsg = '没有选择文件'; break;
                case UPLOAD_ERR_NO_TMP_DIR: $errorMsg = '服务器临时目录不存在'; break;
                case UPLOAD_ERR_CANT_WRITE: $errorMsg = '无法写入文件'; break;
                case UPLOAD_ERR_EXTENSION: $errorMsg = '文件上传被扩展阻止'; break;
            }
        }
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    }
    exit();
}

// 检查是否是获取音乐列表的请求
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    if (ob_get_level()) ob_clean();
    
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    $musicDir = 'music/';
    
    // 检查目录是否存在，如果不存在则创建
    if (!is_dir($musicDir)) {
        if (!@mkdir($musicDir, 0755, true)) {
            echo json_encode(['error' => '无法创建音乐目录: ' . $musicDir]);
            exit();
        }
    }
    
    // 检查目录是否可读
    if (!is_readable($musicDir)) {
        echo json_encode(['error' => '音乐目录不可读: ' . $musicDir]);
        exit();
    }

    $tracks = array();
    
    // 使用glob函数获取MP3文件列表（更可靠的方法）
    $mp3Files = @glob($musicDir . '*.mp3');
    
    if ($mp3Files !== false) {
        foreach ($mp3Files as $filePath) {
            $entry = basename($filePath);
            // 获取完整文件名（不含扩展名）
            $filename = pathinfo($entry, PATHINFO_FILENAME);
            
            $tracks[] = array(
                'title' => $filename,
                'file' => $entry,
                'artist' => '未知艺术家'
            );
        }
    } else {
        echo json_encode(['error' => '无法扫描音乐目录']);
        exit();
    }

    // 按标题排序
    @usort($tracks, function($a, $b) {
        return strcmp($a['title'], $b['title']);
    });

    echo json_encode($tracks, JSON_UNESCAPED_UNICODE);
    exit();
}

// 处理音乐文件播放请求
if (isset($_GET['play'])) {
    $file = $_GET['play'];
    $musicDir = 'music/';
    $filePath = $musicDir . basename($file);
    
    if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'mp3' && is_readable($filePath)) {
        if (ob_get_level()) ob_clean();
        
        header('Content-Type: audio/mpeg');
        header('Content-Length: ' . filesize($filePath));
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache');
        
        readfile($filePath);
        exit();
    } else {
        http_response_code(404);
        echo json_encode(['error' => '文件未找到']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在线音乐播放器</title>
    <style>
        * {margin: 0;padding: 0;box-sizing: border-box;}
        body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;background: #202020;color: #ffffff;min-height: 100vh;padding: 20px;}
        .container {max-width: 800px;margin: 0 auto;}
        header {text-align: center;padding: 20px 0;margin-bottom: 30px;display: flex;justify-content: space-between;align-items: center;flex-wrap: wrap;}
        header h1 {font-size: 2.5rem;font-weight: 300;letter-spacing: 1px;}
        .upload-section {background: rgba(255, 255, 255, 0.05);backdrop-filter: blur(10px);border-radius: 8px;padding: 20px;box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);margin-bottom: 30px;border: 1px solid rgba(255, 255, 255, 0.08);}
        .upload-form {display: flex;gap: 10px;align-items: center;flex-wrap: wrap;}
        .upload-form input[type="file"] {flex: 1;min-width: 200px;padding: 10px;border-radius: 4px;border: 1px solid rgba(255, 255, 255, 0.15);background: rgba(0, 0, 0, 0.2);color: #ffffff;}
        .upload-form button {background: #0078d4;color: white;border: none;padding: 10px 20px;border-radius: 4px;cursor: pointer;font-size: 16px;transition: background 0.3s;}
        .upload-form button:hover {background: #005a9e;}
        .player-container {background: rgba(255, 255, 255, 0.05);backdrop-filter: blur(10px);border-radius: 8px;padding: 30px;box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);margin-bottom: 30px;text-align: center;border: 1px solid rgba(255, 255, 255, 0.08);}
        .album-art {display: flex;justify-content: center;margin-bottom: 20px;}
        .album-placeholder {width: 200px;height: 200px;border-radius: 50%;background: rgba(255, 255, 255, 0.08);display: flex;align-items: center;justify-content: center;font-size: 4rem;margin: 0 auto;box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);}
        .track-info {margin-bottom: 25px;}
        .track-info h2 {font-size: 1.8rem;font-weight: 400;margin-bottom: 10px;}
        .track-info p {font-size: 1.2rem;color: rgba(255, 255, 255, 0.7);}
        .player-controls {margin-bottom: 20px;}
        audio {width: 100%;outline: none;}
        audio::-webkit-media-controls-panel {background: rgba(255, 255, 255, 0.05);}
        .playlist-container {background: rgba(255, 255, 255, 0.05);backdrop-filter: blur(10px);border-radius: 8px;padding: 20px;box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);border: 1px solid rgba(255, 255, 255, 0.08);}
        .playlist-container h3 {font-size: 1.5rem;font-weight: 400;margin-bottom: 15px;padding-bottom: 10px;border-bottom: 1px solid rgba(255, 255, 255, 0.1);}
        #playlist {list-style: none;}
        #playlist li {padding: 12px 15px;border-bottom: 1px solid rgba(255, 255, 255, 0.08);cursor: pointer;transition: all 0.3s ease;display: flex;align-items: center;}
        #playlist li:last-child {border-bottom: none;}
        #playlist li:hover {background: rgba(255, 255, 255, 0.1);border-radius: 4px;}
        #playlist li.playing {background: rgba(0, 120, 212, 0.2);font-weight: bold;border-radius: 4px;}
        #playlist li::before {content: "🎵";margin-right: 10px;}
        .error-message {color: #ff6b6b;background: rgba(255, 107, 107, 0.1);padding: 10px;border-radius: 4px;margin-top: 10px;}
        .success-message {color: #6bff8d;background: rgba(107, 255, 141, 0.1);padding: 10px;border-radius: 4px;margin-top: 10px;}
        .info-message {color: #6bcbff;background: rgba(107, 203, 255, 0.1);padding: 10px;border-radius: 4px;margin-top: 10px;}
        @media (max-width: 600px) {
            .container {padding: 10px;}
            header {flex-direction: column;gap: 15px;}
            header h1 {font-size: 2rem;}
            .player-container {padding: 20px;}
            .album-placeholder {width: 150px;height: 150px;font-size: 3rem;}
            .track-info h2 {font-size: 1.5rem;}
            .upload-form {flex-direction: column;}
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🎵 在线音乐播放器</h1>
        </header>
        
        <div class="upload-section">
            <h3>上传音乐</h3>
            <form class="upload-form" id="upload-form">
                <input type="file" id="music-file" name="music" accept=".mp3" required>
                <button type="submit">上传</button>
            </form>
            <div id="upload-message"></div>
        </div>
        
        <div class="player-container">
            <div class="album-art">
                <div class="album-placeholder">
                    <span>♪</span>
                </div>
            </div>
            
            <div class="track-info">
                <h2 id="track-title">请选择音乐</h2>
                <p id="track-artist">-</p>
            </div>
            
            <div class="player-controls">
                <audio id="audio-player" controls></audio>
            </div>
        </div>
        
        <div class="playlist-container">
            <h3>音乐列表</h3>
            <ul id="playlist">
                <!-- 音乐列表将通过PHP动态加载 -->
            </ul>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const audioPlayer = document.getElementById('audio-player');
            const trackTitle = document.getElementById('track-title');
            const trackArtist = document.getElementById('track-artist');
            const playlist = document.getElementById('playlist');
            const uploadForm = document.getElementById('upload-form');
            const uploadMessage = document.getElementById('upload-message');
            
            loadPlaylist();
            
            window.addEventListener('offline', () => {
                showMessage('网络已断开，请检查您的网络连接', 'error');
            });
            
            window.addEventListener('online', () => {
                showMessage('网络已恢复', 'success');
                loadPlaylist();
            });
            
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const fileInput = document.getElementById('music-file');
                const file = fileInput.files[0];
                
                if (!file) {
                    showMessage('请选择一个文件', 'error');
                    return;
                }
                
                if (!file.name.toLowerCase().endsWith('.mp3')) {
                    showMessage('请选择MP3文件', 'error');
                    return;
                }
                
                const formData = new FormData();
                formData.append('music', file);
                
                showMessage('上传中...', 'info');
                
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        loadPlaylist();
                        fileInput.value = '';
                    } else {
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('上传失败:', error);
                    showMessage('上传失败，请重试', 'error');
                });
            });
            
            function showMessage(message, type) {
                uploadMessage.textContent = message;
                uploadMessage.className = type + '-message';
                
                if (type !== 'info') {
                    setTimeout(() => {
                        uploadMessage.textContent = '';
                        uploadMessage.className = '';
                    }, 3000);
                }
            }
            
            function loadPlaylist() {
                fetch('index.php?action=list')
                    .then(response => {
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            throw new Error('服务器响应不是有效的JSON格式，Content-Type: ' + contentType);
                        }
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('JSON解析失败，原始响应:', text);
                                throw new Error('JSON解析失败: ' + e.message);
                            }
                        });
                    })
                    .then(data => {
                        playlist.innerHTML = '';
                        
                        if (data && data.error) {
                            playlist.innerHTML = '<li class="error-message">错误: ' + data.error + '</li>';
                            return;
                        }
                        
                        if (!Array.isArray(data)) {
                            playlist.innerHTML = '<li class="error-message">数据格式错误: 期望数组但收到 ' + typeof data;
                            if (typeof data === 'object') {
                                playlist.innerHTML += ' (' + JSON.stringify(data) + ')';
                            }
                            playlist.innerHTML += '</li>';
                            console.error('收到的数据:', data);
                            return;
                        }
                        
                        if (data.length === 0) {
                            playlist.innerHTML = '<li>暂无音乐文件</li>';
                            return;
                        }
                        
                        data.forEach(track => {
                            const li = document.createElement('li');
                            li.textContent = track.title;
                            li.dataset.src = 'index.php?play=' + encodeURIComponent(track.file);
                            li.dataset.artist = track.artist || '未知艺术家';
                            
                            li.addEventListener('click', function() {
                                playTrack(this);
                            });
                            
                            playlist.appendChild(li);
                        });
                    })
                    .catch(error => {
                        console.error('获取音乐列表失败:', error);
                        playlist.innerHTML = '<li class="error-message">加载音乐列表失败: ' + error.message + '</li>';
                    });
            }
            
            function playTrack(trackElement) {
                document.querySelectorAll('#playlist li').forEach(li => {
                    li.classList.remove('playing');
                });
                
                trackElement.classList.add('playing');
                
                const src = trackElement.dataset.src;
                const title = trackElement.textContent;
                const artist = trackElement.dataset.artist;
                
                trackTitle.textContent = title;
                trackArtist.textContent = artist;
                
                audioPlayer.src = src;
                audioPlayer.play()
                    .then(() => {
                        console.log('开始播放:', title);
                    })
                    .catch(error => {
                        console.error('播放失败:', error);
                        showMessage('播放失败，请检查文件格式', 'error');
                    });
            }
            
            audioPlayer.addEventListener('ended', function() {
                const currentTrack = document.querySelector('#playlist li.playing');
                if (currentTrack) {
                    currentTrack.classList.remove('playing');
                    
                    const nextTrack = currentTrack.nextElementSibling;
                    
                    if (nextTrack) {
                        playTrack(nextTrack);
                    }
                }
            });
        });
    </script>
</body>
</html>