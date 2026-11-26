{{-- App version under the sidebar user block, both panels (theme: .sta-app-version). --}}
<div class="sta-app-version">{{ implode(' · ', array_filter(['Version '.config('app.version'), config('app.build')])) }}</div>
