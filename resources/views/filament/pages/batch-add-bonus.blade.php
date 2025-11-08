<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded-lg p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-warning-600 dark:text-warning-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-warning-800 dark:text-warning-200">
                        注意事项
                    </h3>
                    <div class="mt-2 text-sm text-warning-700 dark:text-warning-300">
                        <ul class="list-disc list-inside space-y-1">
                            <li>仅站长（Sysop）有权限使用此功能</li>
                            <li>每行输入一个用户ID（纯数字），支持批量操作</li>
                            <li>所有用户将获得相同数量的魔力值</li>
                            <li>操作将记录在魔力值日志中，便于追溯</li>
                            <li>请谨慎操作，建议先测试小批量用户</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <form wire:submit="submit">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button
                    type="submit"
                    size="lg"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    批量发放魔力值
                </x-filament::button>
            </div>
        </form>

        <div class="bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-800 rounded-lg p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-info-600 dark:text-info-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-info-800 dark:text-info-200">
                        使用示例
                    </h3>
                    <div class="mt-2 text-sm text-info-700 dark:text-info-300 font-mono">
                        <div class="bg-white dark:bg-gray-900 rounded p-3 border border-info-300 dark:border-info-700">
                            用户ID列表：<br>
                            1<br>
                            100<br>
                            256<br>
                            <br>
                            增加魔力值：1000<br>
                            备注说明：2024年11月活动奖励
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

