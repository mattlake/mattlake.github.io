@extends('_layouts.main')

@section('body')
    @foreach ($posts->where('featured', true) as $featuredPost)
        <div class="w-full mb-6">
            @if ($featuredPost->cover_image)
                <div class="mb-6">
                    <img src="{{ $featuredPost->cover_image }}" alt="{{ $featuredPost->title }} cover image"
                         class="max-h-64 mx-auto">
                </div>
            @endif

            <p class="text-gray-700 font-medium my-2">
                {{ $featuredPost->getDate()->format('F j, Y') }}
            </p>

            <h2 class="text-3xl mt-0">
                <a href="{{ $featuredPost->getUrl() }}" title="Read {{ $featuredPost->title }}"
                   class="text-gray-900 font-extrabold hover:text-teal-500">
                    {{ $featuredPost->title }}
                </a>
            </h2>

            <p class="mt-0 mb-4">{!! $featuredPost->getExcerpt() !!}</p>

            @if ($featuredPost->categories)
                <div class="mb-4">
                    @foreach ($featuredPost->categories as $i => $category)
                        <a
                                href="{{ '/blog/categories/' . $category }}"
                                title="View posts in {{ $category }}"
                                class="inline-block bg-gray-300 hover:bg-teal-500 hover:text-white leading-loose tracking-wide text-gray-800 uppercase text-xs font-semibold rounded mr-4 px-3 pt-px"
                        >{{ $category }}</a>
                    @endforeach
                </div>
            @endif

            <a href="{{ $featuredPost->getUrl() }}" title="Read - {{ $featuredPost->title }}"
               class="uppercase tracking-wide mb-4 text-teal-500">
                Read
            </a>
        </div>

        @if (! $loop->last)
            <hr class="border-b my-6">
        @endif
    @endforeach

    @foreach ($posts->where('featured', false)->take(6)->chunk(2) as $row)
        <div class="flex flex-col md:flex-row md:-mx-6">
            @foreach ($row as $post)
                <div class="w-full md:w-1/2 md:mx-6">
                    @include('_components.post-preview-inline')
                </div>

                @if (! $loop->last)
                    <hr class="block md:hidden w-full border-b mt-2 mb-6">
                @endif
            @endforeach
        </div>

        @if (! $loop->last)
            <hr class="w-full border-b mt-2 mb-6">
        @endif
    @endforeach
@stop
