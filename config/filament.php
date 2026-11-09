<?php

return [
    // Read by Filament's file upload for openable()/downloadable() URLs on private disks.
    // ponytail: Filament still applies ->endOfHour() to now()+this, so real lifetime is until the end of that hour (up to ~60 min).
    'temporary_file_url_expiry_minutes' => 5,
];
