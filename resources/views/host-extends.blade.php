{{-- Page in a host layout that is extended: the page goes in its content section, the title in its title sections, fixed text in other sections. --}}
@extends($layout['view'])
@foreach ($layout['title_sections'] ?? ['title'] as $titleSection)
@section($titleSection, $title)
@endforeach
@foreach ($layout['sections'] ?? [] as $fixedSection => $text)
@section($fixedSection, $text)
@endforeach
@section($layout['section'] ?? 'content')
@include('stats::partials.embedded')
@endsection
