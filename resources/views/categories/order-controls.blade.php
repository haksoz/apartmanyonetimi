<div class="flex items-center gap-0.5 whitespace-nowrap">
    <span aria-hidden="true" title="Sürükleyerek sırala" class="cursor-grab px-1 text-slate-400 active:cursor-grabbing">⠿</span>
    <div class="flex items-center gap-0.5">
        <button type="button" data-move="up" title="Yukarı taşı" aria-label="{{ $category->name }} yukarı taşı" class="inline-flex h-7 w-7 items-center justify-center rounded text-sm leading-none text-slate-500 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-25 disabled:cursor-default">↑</button>
        <button type="button" data-move="down" title="Aşağı taşı" aria-label="{{ $category->name }} aşağı taşı" class="inline-flex h-7 w-7 items-center justify-center rounded text-sm leading-none text-slate-500 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-25 disabled:cursor-default">↓</button>
    </div>
</div>
