@if (session('status'))
    <div class="toast fade-in" role="status" x-data="toast">{{ session('status') }}</div>
@endif
