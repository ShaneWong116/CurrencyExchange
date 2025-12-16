@php
    $images = $getRecord()->images ?? collect();
@endphp

@if($images->isEmpty())
    <div class="text-gray-500 text-sm">暂无图片</div>
@else
    <div class="flex flex-wrap gap-3">
        @foreach($images as $image)
            @php
                $url = route('api.images.show.public', ['uuid' => $image->uuid]);
            @endphp
            <div class="block cursor-pointer" onclick="openFullscreenImage('{{ $url }}')">
                <img 
                    src="{{ $url }}" 
                    alt="{{ $image->original_name }}"
                    class="max-w-[200px] max-h-[150px] rounded-lg shadow-md hover:shadow-lg hover:opacity-80 transition-all"
                    style="object-fit: cover;"
                />
            </div>
        @endforeach
    </div>
    <div class="mt-2 text-xs text-gray-400">
        共 {{ $images->count() }} 张图片，点击可查看大图
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
@endif
