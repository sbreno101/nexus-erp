@props(['href', 'icon', 'active' => false])

<a href="{{ $href }}" @class(['nav-link', 'nav-link-active' => $active]) @if ($active) aria-current="page" @endif>
    <x-icon :name="$icon" class="size-5" />
    <span class="truncate">{{ $slot }}</span>
</a>
