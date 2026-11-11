@extends('stats::layout')
@section('title', $site->name)
@section('content')
@include('stats::partials.dashboard')
@endsection

@push('scripts')
@include('stats::partials.scripts')
@endpush
