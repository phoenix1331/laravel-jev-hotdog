<?php

declare(strict_types=1);

use App\Hotdog\Verdict;

it('labels each verdict for the banner', function (Verdict $verdict, string $label) {
    expect($verdict->label())->toBe($label);
})->with([
    [Verdict::Hotdog, 'HOTDOG'],
    [Verdict::NotHotdog, 'NOT HOTDOG'],
    [Verdict::Unsure, 'NOT SURE'],
]);

it('uses the expected.json values as backing values', function () {
    expect(Verdict::from('hotdog'))->toBe(Verdict::Hotdog)
        ->and(Verdict::from('not_hotdog'))->toBe(Verdict::NotHotdog);
});
