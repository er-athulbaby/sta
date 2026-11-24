{{-- Alpine x-data members for the List / Grid choice of the Long List and Short List: remembered per user in localStorage
     (may be unavailable, so every access is guarded); anything but 'grid' is the List view, so the pressed button always matches. --}}
view: (() => { try { return localStorage.getItem('longListView.{{ auth()->id() }}') === 'grid' ? 'grid' : 'list' } catch (e) { return 'list' } })(),
init() { this.$watch('view', v => { try { localStorage.setItem('longListView.{{ auth()->id() }}', v) } catch (e) {} }) },
