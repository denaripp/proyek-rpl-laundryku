@php
    // Tahap 17: welcome.blade.php is legacy Laravel default (Tailwind).
    // Real landing page is rendered by PageController@home.
    // Redirect to keep backward compatibility without serving dead Tailwind markup.
    return redirect()->route('home');
@endphp
