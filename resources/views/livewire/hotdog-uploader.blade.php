@php
    $banner = [
        'hotdog' => 'bg-relish text-white',
        'not_hotdog' => 'bg-ketchup text-white',
        'unsure' => 'bg-mustard text-ink',
    ];
@endphp

<main
    class="pb-16"
    x-data="{ top() { window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }) } }"
    x-on:hotdog-checked.window="top()"
>
    <header class="mx-auto flex max-w-2xl items-baseline justify-between gap-4 px-4 pt-6 sm:px-6 sm:pt-10">
        <p class="font-display text-2xl font-extrabold tracking-tight">{{ config('app.name') }}</p>

        @if ($fakeMode)
            <p data-test="fake-badge" class="shrink-0 rounded-full border border-mustard-deep/40 bg-mustard/15 px-3 py-1 text-sm font-medium text-ink" title="JEV_FAKE is on: answers are made up locally and no photo leaves this machine.">
                Fake mode
            </p>
        @endif
    </header>

    @if ($result === null)
        <section class="mx-auto mt-8 max-w-2xl px-4 sm:mt-12 sm:px-6">
            <div
                x-data="{ over: false }"
                x-on:dragover.prevent="over = true"
                x-on:dragleave="over = false"
                x-on:drop="over = false"
                wire:loading.class="pointer-events-none"
                wire:target="photo, trySample"
                class="drop-zone relative flex min-h-80 outline-offset-4 has-[input:focus-visible]:outline-4 has-[input:focus-visible]:outline-ink flex-col justify-center rounded-sm px-6 py-12 transition-colors sm:min-h-96 sm:px-10"
                :class="over ? 'bg-mustard/15' : 'bg-transparent'"
            >
                <input
                    id="photo"
                    type="file"
                    wire:model="photo"
                    accept="{{ $accept }}"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    aria-describedby="photo-hint"
                >

                <div wire:loading.remove wire:target="photo, trySample">
                    <label for="photo" class="block font-display text-5xl leading-[0.95] font-black tracking-tight sm:text-7xl">
                        Drop a photo. Is it a hot dog?
                    </label>
                    <p class="mt-4 text-lg text-ink-soft">Or tap to choose one, or take one with your camera.</p>
                    <p id="photo-hint" class="mt-8 text-sm text-ink-soft">JPEG, PNG, WebP, GIF or HEIC, up to {{ $maxUploadMb }} MB.</p>
                </div>

                <div wire:loading.flex wire:target="photo, trySample" class="hidden flex-col gap-6" role="status">
                    <p class="font-display text-5xl leading-none font-black tracking-tight sm:text-7xl">Looking for a hot dog</p>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-mustard/20">
                        <div class="checking-bar h-full w-2/5 rounded-full bg-mustard"></div>
                    </div>
                </div>
            </div>

            @error('photo')
                <p role="alert" data-test="error" class="mt-6 border-l-4 border-ketchup pl-4 text-base text-ink">{{ $message }}</p>
            @enderror

            @if ($failure !== null)
                <p role="alert" data-test="error" class="mt-6 border-l-4 border-ketchup pl-4 text-base text-ink">{{ $failure }}</p>
            @endif

            @if ($samples !== [])
                <div class="mt-10">
                    <h2 class="font-display text-2xl font-extrabold tracking-tight">No photo handy? Try one of these</h2>
                    <p class="mt-1 text-sm text-ink-soft">Some are easy. Some are corn dogs.</p>

                    <ul class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
                        @foreach ($samples as $sample)
                            <li wire:key="sample-{{ $sample->file }}">
                                <button
                                    type="button"
                                    wire:click="trySample('{{ $sample->file }}')"
                                    x-on:click="top()"
                                    wire:loading.attr="disabled"
                                    wire:target="photo, trySample"
                                    data-test="sample"
                                    class="group block w-full text-left focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-ink"
                                >
                                    <img
                                        src="{{ route('samples.thumbnail', $sample->file) }}"
                                        alt=""
                                        loading="lazy"
                                        class="aspect-square w-full rounded-sm bg-rule object-cover transition group-hover:opacity-80"
                                    >
                                    <span class="mt-1 block truncate text-sm text-ink-soft group-hover:text-ink">{{ $sample->label() }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @else
        <section aria-live="polite" class="mt-8 sm:mt-12">
            <h1
                data-test="verdict"
                data-verdict="{{ $result['verdict'] }}"
                class="verdict-stamp {{ $banner[$result['verdict']] }} px-3 py-6 text-center font-display text-[clamp(3.5rem,19vw,13rem)] leading-[0.85] font-black tracking-tight sm:py-8"
            >
                {{ $result['label'] }}
            </h1>

            <div class="mx-auto mt-8 max-w-2xl px-4 sm:px-6">
                <dl class="grid grid-cols-3 gap-4 border-b border-rule pb-6">
                    <div>
                        <dt class="text-sm text-ink-soft">Confidence</dt>
                        <dd data-test="confidence" class="font-display text-3xl font-extrabold sm:text-4xl">{{ number_format($result['confidence'] * 100, 1) }}%</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-soft">Model</dt>
                        <dd class="mt-2 text-base font-medium break-words">{{ $result['model'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-soft">Latency</dt>
                        <dd class="mt-2 text-base font-medium">{{ $result['latencyMs'] }} ms</dd>
                    </div>
                </dl>

                <figure class="mt-6">
                    <div class="relative">
                        <img
                            src="{{ $result['image'] }}"
                            width="{{ $result['width'] }}"
                            height="{{ $result['height'] }}"
                            alt="The photo you dropped{{ $result['box'] !== null ? ', with a box around the hot dog' : '' }}"
                            class="block h-auto w-full rounded-sm"
                        >

                        @if ($result['box'] !== null)
                            <div
                                data-test="box"
                                class="absolute rounded-sm border-4 border-relish shadow-[0_0_0_2px_white]"
                                style="top: {{ $result['box']['top'] }}; left: {{ $result['box']['left'] }}; width: {{ $result['box']['width'] }}; height: {{ $result['box']['height'] }};"
                            >
                                <span class="absolute -top-8 -left-1 rounded-sm bg-relish px-2 py-0.5 text-sm font-semibold whitespace-nowrap text-white">
                                    Hot dog
                                </span>
                            </div>
                        @endif
                    </div>

                    @if ($result['verdict'] === 'unsure')
                        <figcaption class="mt-3 text-sm text-ink-soft">Somewhere in the middle. A clearer, closer photo usually settles it.</figcaption>
                    @endif
                </figure>

                <button
                    type="button"
                    wire:click="startOver"
                    class="mt-8 w-full rounded-sm bg-ink px-6 py-4 font-display text-2xl font-extrabold tracking-tight text-white hover:bg-ink/90 focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-mustard sm:w-auto"
                >
                    Try another photo
                </button>
            </div>
        </section>
    @endif
</main>
