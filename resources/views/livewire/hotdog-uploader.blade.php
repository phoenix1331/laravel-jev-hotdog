<main class="mx-auto max-w-2xl p-4">
    @if ($fakeMode)
        <p data-test="fake-badge">fake mode</p>
    @endif

    @if ($result === null)
        <label for="photo">Drop a photo. Is it a hot dog?</label>
        <input id="photo" type="file" wire:model="photo" accept="{{ $accept }}">
        <p>JPEG, PNG, WebP, GIF or HEIC, up to {{ $maxUploadMb }} MB.</p>

        <p wire:loading wire:target="photo">Checking...</p>
    @else
        <div class="relative">
            <img src="{{ $result['image'] }}" width="{{ $result['width'] }}" height="{{ $result['height'] }}" alt="Your photo">

            @if ($result['box'] !== null)
                <div
                    data-test="box"
                    class="absolute border-4"
                    style="top: {{ $result['box']['top'] }}; left: {{ $result['box']['left'] }}; width: {{ $result['box']['width'] }}; height: {{ $result['box']['height'] }};"
                ></div>
            @endif
        </div>

        <h1 data-test="verdict" data-verdict="{{ $result['verdict'] }}">{{ $result['label'] }}</h1>

        <dl>
            <dt>Confidence</dt>
            <dd data-test="confidence">{{ number_format($result['confidence'] * 100, 1) }}%</dd>
            <dt>Model</dt>
            <dd>{{ $result['model'] }}</dd>
            <dt>Latency</dt>
            <dd>{{ $result['latencyMs'] }} ms</dd>
        </dl>

        <button type="button" wire:click="startOver">Try another</button>
    @endif

    @error('photo')
        <p role="alert" data-test="error">{{ $message }}</p>
    @enderror

    @if ($failure !== null)
        <p role="alert" data-test="error">{{ $failure }}</p>
    @endif
</main>
