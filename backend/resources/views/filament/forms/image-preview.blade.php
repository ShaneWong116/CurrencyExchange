@if($getRecord() && $getRecord()->isImage() && $getRecord()->file_content)
    @php
        $imageData = 'data:' . $getRecord()->mime_type . ';base64,' . $getRecord()->file_content;
        $uniqueId = 'img-modal-' . $getRecord()->id;
    @endphp
    <div>
        <img src="{{ $imageData }}" 
             alt="{{ $getRecord()->original_name }}"
             class="rounded border shadow-sm cursor-pointer hover:opacity-80 transition-opacity"
             style="max-width: 300px; max-height: 200px; object-fit: contain;"
             onclick="openFullscreenImage('{{ $imageData }}')" />
        <p class="mt-1 text-xs text-gray-400">点击查看原图 ({{ $getRecord()->width }} × {{ $getRecord()->height }})</p>
    </div>
    
    <script>
    function openFullscreenImage(src) {
        // 移除已存在的模态框
        const existing = document.getElementById('fullscreen-image-modal');
        if (existing) existing.remove();
        
        // 创建模态框
        const modal = document.createElement('div');
        modal.id = 'fullscreen-image-modal';
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.9);z-index:999999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
        modal.onclick = function() { this.remove(); };
        
        // 创建图片容器
        const container = document.createElement('div');
        container.style.cssText = 'position:relative;';
        container.onclick = function(e) { e.stopPropagation(); };
        
        // 创建图片
        const img = document.createElement('img');
        img.src = src;
        img.style.cssText = 'max-width:90vw;max-height:90vh;object-fit:contain;border-radius:4px;';
        
        // 创建关闭按钮
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '×';
        closeBtn.style.cssText = 'position:absolute;top:-15px;right:-15px;width:30px;height:30px;border-radius:50%;background:white;border:none;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.3);';
        closeBtn.onclick = function() { modal.remove(); };
        
        container.appendChild(img);
        container.appendChild(closeBtn);
        modal.appendChild(container);
        document.body.appendChild(modal);
        
        // ESC 关闭
        const escHandler = function(e) {
            if (e.key === 'Escape') {
                modal.remove();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    }
    </script>
@else
    <div class="p-4 bg-gray-100 dark:bg-gray-800 border rounded">
        <p class="text-gray-500 dark:text-gray-400">无图片预览</p>
    </div>
@endif
