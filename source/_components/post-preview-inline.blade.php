<div class="flex flex-col mb-4">
    <p class="text-gray-700 font-medium my-2">
        {{ $post->getDate()->format('F j, Y') }}
    </p>

    <h2 class="text-3xl mt-0">
        <a
                href="{{ $post->getUrl() }}"
                title="Read more - {{ $post->title }}"
                class="text-gray-900 font-extrabold hover:text-teal-500"
        >{{ $post->title }}</a>
    </h2>

    <p class="mb-4 mt-0">{!! $post->getExcerpt(200) !!}</p>

    @if ($post->categories)
        <div class="mb-4">
            @foreach ($post->categories as $i => $category)
                <a
                        href="{{ '/blog/categories/' . $category }}"
                        title="View posts in {{ $category }}"
                        class="inline-block bg-gray-300 hover:bg-teal-500 hover:text-white leading-loose tracking-wide text-gray-800 uppercase text-xs font-semibold rounded mr-4 px-3 pt-px"
                >{{ $category }}</a>
            @endforeach
        </div>
    @endif

    <a
            href="{{ $post->getUrl() }}"
            title="Read more - {{ $post->title }}"
            class="uppercase font-semibold tracking-wide mb-2 text-teal-500"
    >Read</a>
</div>
