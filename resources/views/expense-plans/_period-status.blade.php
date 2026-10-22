<span @class([
    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
    'bg-emerald-50 text-emerald-700' => $p['status'] === 'complete',
    'bg-slate-100 text-slate-500' => in_array($p['status'], ['stopped', 'skipped'], true),
    'bg-slate-100 text-slate-600' => $p['status'] === 'not_generated',
])>{{ match ($p['status']) {
    'complete' => 'Oluşturuldu',
    'skipped' => 'Atlandı',
    'stopped' => 'Durduruldu',
    default => 'Oluşturulmadı',
} }}</span>
