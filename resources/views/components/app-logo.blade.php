@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="The Fitness Club" {{ $attributes }} />
@else
    <flux:brand name="The Fitness Club" {{ $attributes }} />
@endif
