<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">通知の設定</h2>
        <p class="mt-1 text-sm text-gray-600">当日の正午になっても代講者が見つからないときは、その日に授業が入っていない先生へ「至急」のメールを送ります。</p>
    </header>

    <form method="post" action="{{ route('profile.notifications') }}" class="mt-6 space-y-4">
        @csrf
        @method('put')

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="hidden" name="accepts_urgent_requests" value="0">
            <input type="checkbox" name="accepts_urgent_requests" value="1" class="rounded border-gray-300 text-indigo-600"
                   @checked($user->accepts_urgent_requests)>
            急な代講の連絡を受け取る
        </label>

        <p class="text-xs text-gray-500">前日の夜の「明日の代講」のリマインドは、この設定に関係なく届きます。</p>

        <div class="flex items-center gap-4">
            <x-primary-button>保存</x-primary-button>
            @if (session('status') === 'notifications-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600">保存しました</p>
            @endif
        </div>
    </form>
</section>
