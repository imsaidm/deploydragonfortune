@extends('layouts.app')

@section('title', 'Creator Strategies - ' . ucfirst($creator))

@section('content')
<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h2 class="h4 mb-0 fw-bold">Strategies by {{ ucfirst($creator) }}</h2>
        <p class="text-muted mb-0">No strategies found for this creator.</p>
    </div>
</div>
@endsection