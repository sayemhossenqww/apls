@extends('layouts.app')
@section('title', __('Edit') . ' ' . __('Merchant'))

@section('content')
    <div class="d-flex align-items-center justify-content-center mb-3">
        <div class="h4 mb-0 flex-grow-1">@lang('Edit') @lang('Merchant')</div>
        <x-back-btn href="{{ route('suppliers.index') }}" />
    </div>
    <div class="card">
        <div class="card-body">
            @include('suppliers.partials.form')
        </div>
    </div>
@endsection
