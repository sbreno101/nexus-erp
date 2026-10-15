<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ config('app.name', 'Nexus ERP') }}</title>

{{-- Aplica o tema antes da renderização para evitar "piscar" a tela clara. --}}
<script>
    (function () {
        let theme = null;
        try { theme = localStorage.getItem('nexus-theme'); } catch (e) {}
        const preferred = @js(auth()->user()?->theme?->value ?? 'system');
        const dark = theme ? theme === 'dark'
            : (preferred === 'dark' || (preferred === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches));
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
