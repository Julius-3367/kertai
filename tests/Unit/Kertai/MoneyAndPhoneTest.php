<?php

use App\Domain\Dues\Models\Due;
use App\Domain\Mgr\MerryGoRound;
use App\Support\Money;
use App\Support\Phone;

it('converts shillings typed in forms to cents', function (string $input, int $cents) {
    expect(Money::toCents($input))->toBe($cents);
})->with([
    ['1000', 100000],
    ['1,500', 150000],
    ['1500.5', 150050],
    ['0.05', 5],
    ['KES 2,000.00', 200000],
    ['-300', -30000],
]);

it('refuses text that is not money', function () {
    Money::toCents('ten shillings');
})->throws(InvalidArgumentException::class);

it('formats cents for people', function () {
    expect(Money::format(150050))->toBe('KES 1,500.50')
        ->and(Money::format(-2500))->toBe('-KES 25.00')
        ->and(Money::toShillings(100000))->toBe('1000.00');
});

it('normalises Kenyan mobile numbers', function (?string $input, ?string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['0712345678', '254712345678'],
    ['+254 712 345 678', '254712345678'],
    ['254112345678', '254112345678'],
    ['712345678', '254712345678'],
    ['0720-653-394', '254720653394'],
    ['12345', null],
    [null, null],
]);

it('finds phone numbers in a bank narrative', function () {
    expect(Phone::extractAll('MPS 254711000001 RJK1ABCD12 JOHN 0720653394'))
        ->toBe(['254711000001', '254720653394']);
});

it('fills merry-go-round first, then welfare, then savings', function () {
    $breakdown = ['mgr' => 50000, 'welfare' => 20000, 'savings' => 30000];

    expect(Due::split($breakdown, 0, 60000))->toBe(['mgr' => 50000, 'welfare' => 10000])
        ->and(Due::split($breakdown, 60000, 100000))->toBe(['welfare' => 10000, 'savings' => 30000])
        ->and(Due::split($breakdown, 0, 100000))->toBe($breakdown);
});

it('draws the same ballot order from the same seed', function () {
    $mgr = (new ReflectionClass(MerryGoRound::class))->newInstanceWithoutConstructor();
    $ids = [5, 1, 9, 3, 7];

    $first = $mgr->draw($ids, 'seed-abc');

    expect($mgr->draw(array_reverse($ids), 'seed-abc'))->toBe($first)
        ->and($first)->toHaveCount(5)
        ->and(collect($first)->sort()->values()->all())->toBe([1, 3, 5, 7, 9]);
});
