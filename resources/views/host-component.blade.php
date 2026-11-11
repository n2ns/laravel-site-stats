{{-- Page in a host layout component: the page goes in its slot, the title in its title attribute. --}}
<x-dynamic-component :component="$layout['component']" :title="$title">
@include('stats::partials.embedded')
</x-dynamic-component>
