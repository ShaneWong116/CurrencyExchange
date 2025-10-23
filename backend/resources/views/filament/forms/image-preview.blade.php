@if($getRecord() && $getRecord()->isImage() && $getRecord()->file_content)
    <div class="max-w-lg">
        <img src="data:{{ $getRecord()->mime_type }};base64,{{ $getRecord()->file_content }}" 
             alt="{{ $getRecord()->original_name }}"
             class="w-full h-auto rounded border shadow-sm"
             style="max-height: 400px; object-fit: contain;" />
        <div class="mt-2 text-sm text-gray-600">
            <p><strong>文件名:</strong> {{ $getRecord()->original_name }}</p>
            <p><strong>尺寸:</strong> {{ $getRecord()->width }} × {{ $getRecord()->height }}</p>
            <p><strong>大小:</strong> {{ $getRecord()->getFileSizeFormatted() }}</p>
            <p><strong>类型:</strong> {{ $getRecord()->mime_type }}</p>
        </div>
    </div>
@else
    <div class="p-4 bg-gray-50 border border-gray-200 rounded">
        <p class="text-gray-500">无图片预览</p>
    </div>
@endif
