@if($getRecord()->isImage() && $getRecord()->file_content)
    <img src="data:{{ $getRecord()->mime_type }};base64,{{ $getRecord()->getThumbnail() }}" 
         alt="{{ $getRecord()->original_name }}"
         class="w-12 h-12 object-cover rounded border"
         loading="lazy" />
@else
    <div class="w-12 h-12 bg-gray-100 rounded border flex items-center justify-center">
        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
        </svg>
    </div>
@endif
