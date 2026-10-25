@if (auth()->user()->isTeacher())
    <nav class="flex gap-1 text-sm">
        <a href="{{ route('requests.index') }}" class="rounded-md px-3 py-1.5 {{ request()->routeIs('requests.index') ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">募集中の代講</a>
        <a href="{{ route('requests.mine') }}" class="rounded-md px-3 py-1.5 {{ request()->routeIs('requests.mine') ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">自分の依頼・応募</a>
    </nav>
@endif
