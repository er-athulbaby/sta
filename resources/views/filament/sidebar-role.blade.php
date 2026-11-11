{{-- User block role line, drawn under the name inside the sidebar user menu (theme: .sta-user-role). --}}
@auth
    <span class="sta-user-role">{{ auth()->user()->role->label() }}</span>
@endauth
