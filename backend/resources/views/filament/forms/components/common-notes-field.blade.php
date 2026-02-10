{{-- Common Notes Field Component --}}
@php
    use App\Models\CommonNote;
    
    $user = auth()->user();
    $notes = [];
    
    if ($user) {
        $notes = CommonNote::where('user_id', $user->id)
            ->where('user_type', 'admin')
            ->latest()
            ->get()
            ->map(function($note) {
                return [
                    'id' => $note->id,
                    'content' => $note->content,
                ];
            })
            ->toArray();
    }
@endphp

<div class="fi-fo-field-wrp" style="margin-bottom: 1rem;">
    <div 
        x-data="{ expanded: false, notes: @js($notes) }"
        class="space-y-2"
    >
        {{-- Toggle Button --}}
        <button
            type="button"
            @click="expanded = !expanded"
            class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
        >
            <svg 
                class="w-4 h-4 transition-transform duration-200" 
                :class="{ 'rotate-90': expanded }"
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span>常用备注</span>
            <span class="text-xs text-gray-500" x-show="!expanded && notes.length > 0" x-text="`(${notes.length})`"></span>
        </button>
        
        {{-- Notes Container (Collapsible) --}}
        <div 
            x-show="expanded" 
            x-collapse
            class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700"
        >
            {{-- Empty State --}}
            <div 
                x-show="notes.length === 0" 
                class="text-center py-4"
            >
                <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">暂无常用备注</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">管理员可以在这里添加常用备注</p>
            </div>
            
            {{-- Notes List --}}
            <div 
                x-show="notes.length > 0" 
                class="space-y-2"
            >
                <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto">
                    <template x-for="note in notes" :key="note.id">
                        <div class="inline-flex items-center gap-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1.5 text-sm shadow-sm hover:shadow-md transition-shadow">
                            <button
                                type="button"
                                @click="
                                    let textarea = document.querySelector('textarea[name*=&quot;notes&quot;]') || document.querySelector('textarea[id*=&quot;notes&quot;]') || document.querySelector('textarea[name*=&quot;备注&quot;]') || document.querySelector('textarea[placeholder*=&quot;备注&quot;]') || document.querySelector('textarea[placeholder*=&quot;notes&quot;]') || document.querySelector('form textarea:last-of-type');
                                    if (textarea) {
                                        textarea.value = note.content;
                                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                                        textarea.dispatchEvent(new Event('change', { bubbles: true }));
                                        textarea.focus();
                                    } else {
                                        alert('找不到备注输入框');
                                    }
                                "
                                class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                x-text="note.content"
                            ></button>
                            <button
                                type="button"
                                @click="
                                    if (confirm('确定要删除备注「' + note.content + '」吗？')) {
                                        fetch('/admin/common-notes/' + note.id, {
                                            method: 'DELETE',
                                            headers: {
                                                'Accept': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'X-Requested-With': 'XMLHttpRequest'
                                            },
                                            credentials: 'same-origin'
                                        }).then(response => {
                                            console.log('Delete response status:', response.status);
                                            return response.json();
                                        }).then(data => {
                                            if (data.success) {
                                                notes = notes.filter(n => n.id !== note.id);
                                                alert('删除成功');
                                            } else {
                                                alert('删除失败：' + (data.message || '未知错误'));
                                            }
                                        }).catch(error => {
                                            alert('删除失败：' + error.message);
                                        });
                                    }
                                "
                                class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 ml-1 transition-colors"
                                title="删除"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
            
            {{-- Add button and simple form --}}
            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div x-data="{ showForm: false, newContent: '' }">
                    <button
                        type="button"
                        @click="showForm = !showForm"
                        class="inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 font-medium transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span x-text="showForm ? '取消' : '添加常用备注'"></span>
                    </button>
                    
                    <div x-show="showForm" x-collapse class="mt-3">
                        <div class="space-y-3">
                            <textarea
                                x-model="newContent"
                                rows="2"
                                maxlength="500"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-gray-100 text-sm"
                                placeholder="请输入备注内容（最多500字符）"
                            ></textarea>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500" x-text="newContent.length + ' / 500'"></span>
                                <button
                                    type="button"
                                    @click="
                                        if (!newContent.trim()) {
                                            alert('请输入备注内容');
                                            return;
                                        }
                                        if (newContent.length > 500) {
                                            alert('备注内容不能超过500字符');
                                            return;
                                        }
                                        fetch('/admin/common-notes', {
                                            method: 'POST',
                                            headers: {
                                                'Accept': 'application/json',
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'X-Requested-With': 'XMLHttpRequest'
                                            },
                                            credentials: 'same-origin',
                                            body: JSON.stringify({ content: newContent.trim() })
                                        }).then(response => {
                                            console.log('Response status:', response.status);
                                            console.log('Response headers:', response.headers);
                                            return response.json();
                                        }).then(data => {
                                            if (data.success) {
                                                notes.unshift(data.data);
                                                newContent = '';
                                                showForm = false;
                                                alert('添加成功');
                                            } else {
                                                alert('添加失败：' + (data.message || '未知错误'));
                                            }
                                        }).catch(error => {
                                            alert('添加失败：' + error.message);
                                        });
                                    "
                                    :disabled="!newContent.trim()"
                                    class="px-3 py-1 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    添加
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>