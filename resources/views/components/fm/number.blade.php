{{--
    Input number avec flèches conformes à la DA (les spinners natifs sont masqués globalement).
    Les classes passées vont à l'<input> ; la largeur se règle via `wrap` (ex: wrap="w-16").
--}}
@props(['wrap' => 'w-full'])

<div class="fm-number {{ $wrap }}">
    <input
        type="number"
        {{ $attributes->merge(['class' => 'fm-input']) }}
    >
    <div class="fm-number-arrows">
        <button
            type="button"
            tabindex="-1"
            aria-label="Augmenter"
            onclick="const i = this.closest('.fm-number').querySelector('input'); i.stepUp(); i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true }));"
        >
            <svg viewBox="0 0 12 12" class="h-3 w-3 fill-current"><path d="M6 3.5 10 8H2z"/></svg>
        </button>
        <button
            type="button"
            tabindex="-1"
            aria-label="Diminuer"
            onclick="const i = this.closest('.fm-number').querySelector('input'); i.stepDown(); i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true }));"
        >
            <svg viewBox="0 0 12 12" class="h-3 w-3 fill-current"><path d="M6 8.5 2 4h8z"/></svg>
        </button>
    </div>
</div>
