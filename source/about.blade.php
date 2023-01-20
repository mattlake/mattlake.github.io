---
title: About
description: A little bit about the site
---
@extends('_layouts.main')

@section('body')
    <h1>About</h1>

    <span class="flex rounded-full h-64 w-64 bg-contain mx-auto md:float-right my-6 md:ml-10 overflow-hidden">
        <img src="/assets/img/profile_img.png" alt="Matt Lake portrait">
    </span>

    <p class="mb-6">By day I am a Software Engineer in telecoms in the UK, using mostly .NET and PHP.
    By night I am a husband and father of two. In any spare time I can find, I usually turn to Lego or Xbox.
    My first line of code was on a Commodore 64 when I was in primary school, and had the bug ever since.</p>
@endsection
