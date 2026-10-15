<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">基本情報</h2>
        <p class="mt-1 text-sm text-gray-600">プロフィール画像・氏名・ニックネーム・メールアドレス（代講の通知先）を変更できます。</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="flex items-center gap-4" x-data="{ preview: null }">
            <template x-if="preview">
                <img :src="preview" alt="選んだ画像" class="h-20 w-20 rounded-full object-cover border border-indigo-300">
            </template>
            <template x-if="! preview">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="プロフィール画像" class="h-20 w-20 rounded-full object-cover border border-gray-200">
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 text-2xl text-gray-400">
                        {{ mb_substr($user->displayName(), 0, 1) }}
                    </div>
                @endif
            </template>
            <div class="space-y-2">
                <x-input-label for="avatar" value="プロフィール画像（jpg・png・webp、2MBまで）" />
                <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                       @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                       class="block text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-3 file:py-1.5 file:text-white">
                <p x-show="preview" class="text-xs text-indigo-700">「保存」を押すと、この画像に変わります</p>
                @if ($user->avatar)
                    <label class="inline-flex items-center gap-1 text-sm text-gray-600">
                        <input type="checkbox" name="remove_avatar" value="1" class="rounded border-gray-300"> 画像を削除する
                    </label>
                @endif
                <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
            </div>
        </div>

        <div>
            <x-input-label for="name" value="氏名（姓と名の間にスペース）" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="nickname" value="ニックネーム（任意・シフト表で大きく表示されます）" />
            <x-text-input id="nickname" name="nickname" type="text" class="mt-1 block w-full" :value="old('nickname', $user->nickname)" maxlength="20" />
            <x-input-error class="mt-2" :messages="$errors->get('nickname')" />
        </div>

        <div>
            <x-input-label for="email" value="メールアドレス" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        メールアドレスの確認がまだ済んでいません。
                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            確認メールを再送する
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">確認メールを送りました。</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>保存</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600">保存しました</p>
            @endif
        </div>
    </form>
</section>
