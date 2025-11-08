<x-filament-panels::page>
    <div class="space-y-6">
        <!-- 统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach($this->getStats() as $stat)
                <x-filament::section>
                    <div class="flex items-center gap-3">
                        <x-filament::icon
                            :icon="$stat['icon']"
                            class="h-8 w-8 text-{{ $stat['color'] }}-500"
                        />
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $stat['label'] }}
                            </div>
                            <div class="text-2xl font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400">
                                {{ $stat['value'] }}
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <!-- 排行榜和活动 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 财富排行榜 -->
            <x-filament::section>
                <x-slot name="heading">
                    🏆 财富排行榜 TOP 10
                </x-slot>
                
                <div class="space-y-2">
                    @foreach($this->getTopFarms() as $index => $farm)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                            <div class="flex items-center gap-3">
                                <span class="text-lg font-bold 
                                    @if($index === 0) text-yellow-500
                                    @elseif($index === 1) text-gray-400
                                    @elseif($index === 2) text-orange-500
                                    @else text-gray-500
                                    @endif
                                ">
                                    #{{ $index + 1 }}
                                </span>
                                <span class="font-medium">{{ $farm['username'] }}</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-sm">
                                    <span class="text-yellow-500">⭐</span> {{ $farm['stardust'] }}
                                </span>
                                <span class="text-sm text-gray-500">
                                    Lv.{{ $farm['level'] }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <!-- 最近活动 -->
            <x-filament::section>
                <x-slot name="heading">
                    📊 最近活动
                </x-slot>
                
                <div class="space-y-2 max-h-[600px] overflow-y-auto">
                    @foreach($this->getRecentActivities() as $activity)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $activity['from'] }}</span>
                                <span class="text-gray-500">
                                    {{ $activity['action'] }}
                                </span>
                                <span class="font-medium">{{ $activity['to'] }}</span>
                            </div>
                            <span class="text-xs text-gray-400">
                                {{ $activity['time'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>

