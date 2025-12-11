@php
    $images = $getRecord()->images ?? collect();
@endphp

@if($images->isEmpty())
    <div class="text-gray-500 text-sm">暂无图片</div>
@else
    <div class="flex flex-wrap gap-3">
        @foreach($images as $image)
            @php
                $url = route('api.images.show', $image->uuid);
            @endphp
            <a href="{{ $url }}" target="_blank" class="block">
                <img 
                    src="{{ $url }}" 
                    alt="{{ $image->original_name }}"
                    class="max-w-[200px] max-h-[150px] rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer"
                    style="object-fit: cover;"
                />
            </a>
        @endforeach
    </div>
    <div class="mt-2 text-xs text-gray-400">
        共 {{ $images->count() }} 张图片，点击可查看大图
    </div>
@endif
