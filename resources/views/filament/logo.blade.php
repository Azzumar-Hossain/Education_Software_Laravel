@php
    $settings = null;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            $settings = \App\Models\SiteSetting::first();
        }
    } catch (\Exception $e) {}

    // Pulls English name first, then Bengali, then fallback
    $schoolName = $settings->school_name_en ?? ($settings->school_name_bn ?? 'EduSphere');
    $logoUrl = ($settings && $settings->logo) ? asset('storage/' . $settings->logo) : null;
@endphp

<div class="flex items-center gap-3">
    @php
        $settings = \App\Models\SiteSetting::first();
        $schoolName = $settings->school_name_en ?? $settings->school_name_bn ?? 'EduSphere';
        $logoUrl = ($settings && $settings->logo) ? asset('storage/' . $settings->logo) : null;
    @endphp

    @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="Logo" style="height: 2.5rem; width: auto; border-radius: 0.25rem;">
    @endif
    
    <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
        {{ $schoolName }}
    </span>
</div>