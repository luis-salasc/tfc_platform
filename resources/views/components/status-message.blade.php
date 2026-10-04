@if (session('status'))
    <div data-auto-dismiss class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-800 transition-opacity dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200" role="status">{{ session('status') }}</div>
    <script>window.setTimeout(() => { const message = document.querySelector('[data-auto-dismiss]'); if (message) { message.classList.add('opacity-0'); window.setTimeout(() => message.remove(), 300); } }, 5000);</script>
@endif
